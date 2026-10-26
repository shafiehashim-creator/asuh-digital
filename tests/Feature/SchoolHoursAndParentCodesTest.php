<?php

use App\Enums\AgeGroup;
use App\Enums\Role;
use App\Models\Book;
use App\Models\Child;
use App\Models\Classroom;
use App\Models\Institution;
use App\Models\License;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentProcessor;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
    Setting::set('catalog_open', true);
    Mail::fake();
    Notification::fake();
});

function schoolPupil(array $license = []): Child
{
    $institution = Institution::factory()->create(['name' => 'Tadika Seri Ilmu']);
    License::factory()->for($institution)->create($license);
    $classroom = Classroom::factory()->for($institution)->create(['code' => 'AB7K2Q', 'age_group' => AgeGroup::SixToNine]);
    $pupil = new Child(['nickname' => 'Hana', 'age_group' => AgeGroup::SixToNine, 'avatar' => 'kucing']);
    $pupil->classroom_id = $classroom->id;
    $pupil->save();

    return $pupil;
}

$schoolHours = ['access_days' => [1, 2, 3, 4, 5], 'access_starts' => '07:00', 'access_ends' => '18:00'];

// --- School hours -----------------------------------------------------------------

it('opens a licence any time when no school hours are set', function () {
    $license = License::factory()->create();
    $this->travelTo('2026-11-01 22:00:00'); // Sunday night

    expect($license->isOpenNow())->toBeTrue()->and($license->schoolHoursLabel())->toBeNull();
});

it('knows the school days and hours', function () use ($schoolHours) {
    $license = License::factory()->create($schoolHours);

    $this->travelTo('2026-10-28 10:00:00'); // Wednesday
    expect($license->isOpenNow())->toBeTrue();
    $this->travelTo('2026-10-28 18:30:00');
    expect($license->isOpenNow())->toBeFalse();
    $this->travelTo('2026-10-28 06:59:00');
    expect($license->isOpenNow())->toBeFalse();
    $this->travelTo('2026-10-31 10:00:00'); // Saturday
    expect($license->isOpenNow())->toBeFalse();

    expect($license->schoolHoursLabel())->toBe('Isnin–Jumaat, 7:00 pagi – 6:00 petang');
});

it('lets pupils in during school hours only', function () use ($schoolHours) {
    $pupil = schoolPupil($schoolHours);

    $this->travelTo('2026-10-31 10:00:00'); // Saturday
    $this->post('/kelas', ['code' => 'AB7K2Q']);
    $this->get('/kelas/pilih')->assertSee('dibuka pada waktu sekolah sahaja')->assertSee('Isnin–Jumaat')
        ->assertSee('Minta ibu atau ayah langgan')->assertDontSee('Tekan nama kamu');
    $this->post("/kelas/pilih/{$pupil->id}")->assertRedirect('/kelas/pilih');

    $this->travelTo('2026-11-02 09:00:00'); // Monday
    $this->post("/kelas/pilih/{$pupil->id}")->assertRedirect('/mod-anak');
    $this->get('/mod-anak')->assertOk();
});

it('sends a reading pupil back to the code page when school ends', function () use ($schoolHours) {
    $pupil = schoolPupil($schoolHours);
    $this->travelTo('2026-11-02 17:50:00');
    $this->post('/kelas', ['code' => 'AB7K2Q']);
    $this->post("/kelas/pilih/{$pupil->id}");

    $this->travelTo('2026-11-02 18:05:00');
    $this->get('/mod-anak')->assertRedirect('/kelas');
    $this->get('/kelas')->assertSee('Waktu kelas dah tamat');
});

it('shows the hours and the parent code to teachers', function () use ($schoolHours) {
    $pupil = schoolPupil($schoolHours);
    $institution = $pupil->classroom->institution;
    $teacher = User::factory()->create();
    $teacher->forceFill(['role' => Role::Teacher, 'institution_id' => $institution->id])->save();

    $this->actingAs($teacher)->get('/guru')->assertOk()
        ->assertSee('Isnin–Jumaat')
        ->assertSee($institution->parent_code)
        ->assertSee('diskaun 20%');
});

// --- Parent codes -------------------------------------------------------------------

it('gives every institution a parent code', function () {
    $institution = Institution::factory()->create();

    expect($institution->parent_code)->toStartWith('ASUH-')
        ->and(Institution::findByParentCode(strtolower($institution->parent_code))?->id)->toBe($institution->id);

    $institution->update(['parent_discount_percent' => 0]);
    expect(Institution::findByParentCode($institution->parent_code))->toBeNull();
});

it('takes the school discount off a Langganan Tahunan and records the school', function () {
    Setting::set('preorder_slot_limit', 0); // early bird over: regular price on sale
    $institution = Institution::factory()->create(['parent_code' => 'TADIKA-ILMU', 'parent_discount_percent' => 20]);
    $user = User::factory()->create();

    $this->actingAs($user)->get('/beli/langganan')->assertOk()->assertSee('Ada kod promosi atau kod sekolah?');
    $this->actingAs($user)->post('/beli/langganan', ['payment_method' => 'chip', 'school_code' => 'tadika-ilmu'])->assertRedirect();

    $order = Order::query()->sole();
    expect($order->amount_sen)->toBe(7920) // RM99 - 20%
        ->and($order->discount_sen)->toBe(1980)
        ->and($order->referral_institution_id)->toBe($institution->id);

    $this->actingAs($user)->get("/pesanan/{$order->reference}")->assertSee('Diskaun')->assertSee('RM79.20');

    app(PaymentProcessor::class)->markPaid($order);
    expect($user->activePass())->not->toBeNull();
});

it('rejects an unknown school code', function () {
    Setting::set('preorder_slot_limit', 0);

    $this->actingAs(User::factory()->create())
        ->post('/beli/langganan', ['payment_method' => 'chip', 'promo_code' => 'TIADA'])
        ->assertSessionHasErrors('promo_code');
    expect(Order::query()->count())->toBe(0);
});

it('does not discount single books', function () {
    $institution = Institution::factory()->create(['parent_code' => 'TADIKA-ILMU']);
    $book = Book::factory()->published()->create(['single_price_sen' => 1500]);

    $this->actingAs(User::factory()->create())
        ->post("/beli/buku/{$book->slug}", ['payment_method' => 'chip', 'promo_code' => 'TADIKA-ILMU'])
        ->assertSessionHasErrors(['promo_code' => 'Kod sekolah hanya untuk Langganan Tahunan dan Langganan Plus baharu.']);

    expect(Order::query()->count())->toBe(0);
});

it('counts family sales per school for the admin', function () {
    $institution = Institution::factory()->create(['parent_code' => 'TADIKA-ILMU']);
    Order::factory()->paid()->create(['referral_institution_id' => $institution->id]);
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/institusi')->assertOk()->assertSee('TADIKA-ILMU')->assertSee('Jualan ibu bapa');
});
