<?php

use App\Enums\AgeGroup;
use App\Models\Book;
use App\Models\Child;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Setting;
use App\Models\User;
use App\Services\ParentGate;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Setting::set('catalog_open', true);
    Storage::fake('local');
    Storage::disk('local')->put('books/contoh.pdf', '%PDF-1.4 ujian');
});

function childWithPass(AgeGroup $age = AgeGroup::SixToNine): Child
{
    $parent = User::factory()->create();
    $order = Order::factory()->paid()->create(['user_id' => $parent->id]);
    Pass::query()->create([
        'user_id' => $parent->id, 'plan_id' => $order->plan_id, 'order_id' => $order->id,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
    ]);

    return Child::factory()->for($parent)->create(['nickname' => 'Aisyah', 'age_group' => $age]);
}

function enterChildMode(Child $child): void
{
    test()->actingAs($child->user)->post("/akaun/anak/{$child->id}/mod-anak")->assertRedirect('/mod-anak');
}

// --- Entering and the shelf ----------------------------------------------------

it('shows only books for the child\'s age group', function () {
    $child = childWithPass(AgeGroup::SixToNine);
    Book::factory()->published()->create(['title' => 'Buku Adik', 'age_group' => AgeGroup::SixToNine]);
    Book::factory()->published()->create(['title' => 'Buku Semua', 'age_group' => AgeGroup::Both]);
    Book::factory()->published()->create(['title' => 'Buku Abang', 'age_group' => AgeGroup::TenToThirteen]);
    Book::factory()->create(['title' => 'Buku Draf', 'age_group' => AgeGroup::SixToNine]);

    enterChildMode($child);

    $this->get('/mod-anak')->assertOk()
        ->assertSee('Rak buku Aisyah')
        ->assertSee('Buku Adik')
        ->assertSee('Buku Semua')
        ->assertDontSee('Buku Abang')
        ->assertDontSee('Buku Draf');
});

it('shows no prices, buy buttons or account links', function () {
    $child = childWithPass();
    Book::factory()->published()->create(['age_group' => AgeGroup::SixToNine, 'single_price_sen' => 1590]);

    enterChildMode($child);

    $this->get('/mod-anak')->assertOk()
        ->assertDontSee('RM')
        ->assertDontSee('Beli')
        ->assertDontSee(route('account'))
        ->assertDontSee(route('logout'))
        ->assertDontSee(route('catalog.index'));
});

it('puts only bought books on the shelf when there is no pass', function () {
    $child = Child::factory()->create(['age_group' => AgeGroup::SixToNine]);
    $owned = Book::factory()->published()->create(['title' => 'Dibeli', 'age_group' => AgeGroup::SixToNine]);
    Book::factory()->published()->create(['title' => 'Tidak Dibeli', 'age_group' => AgeGroup::SixToNine]);
    Entitlement::query()->create(['user_id' => $child->user_id, 'book_id' => $owned->id]);

    enterChildMode($child);

    $this->get('/mod-anak')->assertSee('Dibeli')->assertDontSee('Tidak Dibeli');
});

it('cannot enter Mod Anak for another family\'s child', function () {
    $child = Child::factory()->create();

    $this->actingAs(User::factory()->create())->post("/akaun/anak/{$child->id}/mod-anak")->assertNotFound();
});

// --- The lock --------------------------------------------------------------------

it('sends every other page back to the shelf', function (string $method, string $url) {
    enterChildMode(childWithPass());

    $this->call($method, $url)->assertRedirect('/mod-anak');
})->with([
    ['GET', '/akaun'],
    ['GET', '/buku'],
    ['GET', '/langganan'],
    ['GET', '/beli/langganan'],
    ['GET', '/'],
    ['POST', '/logout'],
]);

it('keeps the child signed in when they try to log out', function () {
    $child = childWithPass();
    enterChildMode($child);

    $this->post('/logout');

    $this->assertAuthenticatedAs($child->user);
});

it('opens the reader for shelf books and closes back to the shelf', function () {
    $child = childWithPass(AgeGroup::SixToNine);
    $book = Book::factory()->published()->withFile()->create(['age_group' => AgeGroup::SixToNine]);
    enterChildMode($child);

    $this->get("/baca/{$book->slug}")->assertOk()
        ->assertSee('href="'.route('child-mode.home').'"', false);
});

it('keeps books for another age out of the reader', function () {
    $child = childWithPass(AgeGroup::SixToNine);
    $book = Book::factory()->published()->withFile()->create(['age_group' => AgeGroup::TenToThirteen]);
    enterChildMode($child);

    $this->get("/baca/{$book->slug}")->assertRedirect('/mod-anak');
});

it('ends Mod Anak by itself when the profile is deleted elsewhere', function () {
    $child = childWithPass();
    enterChildMode($child);

    $child->delete();

    $this->get('/akaun')->assertOk();
});

// --- The parent gate -------------------------------------------------------------

it('asks a sum written in words and lets the parent out with the right answer', function () {
    enterChildMode(childWithPass());

    $this->get('/mod-anak/keluar')->assertOk()->assertSee('Berapakah')->assertSee('darab');
    $answer = session('parent_gate_answer');

    $this->post('/mod-anak/keluar', ['answer' => (string) $answer])->assertRedirect('/akaun');
    $this->get('/akaun')->assertOk();
});

it('stays locked after a wrong answer and asks a new question', function () {
    enterChildMode(childWithPass());
    $this->get('/mod-anak/keluar');
    $answer = session('parent_gate_answer');

    $this->post('/mod-anak/keluar', ['answer' => (string) ($answer + 1)])
        ->assertRedirect('/mod-anak/keluar')
        ->assertSessionHasErrors('answer');

    // The old answer is spent and cannot be replayed.
    $this->post('/mod-anak/keluar', ['answer' => (string) $answer])->assertSessionHasErrors('answer');
    $this->get('/akaun')->assertRedirect('/mod-anak');
});

it('uses the parent PIN instead of the sum once set', function () {
    $child = childWithPass();
    $child->user->forceFill(['parent_pin' => '4821'])->save();
    enterChildMode($child);

    $this->get('/mod-anak/keluar')->assertOk()->assertSee('Masukkan PIN')->assertDontSee('Berapakah');

    $this->post('/mod-anak/keluar', ['answer' => '1234'])->assertSessionHasErrors('answer');
    $this->get('/akaun')->assertRedirect('/mod-anak');

    $this->post('/mod-anak/keluar', ['answer' => '4821'])->assertRedirect('/akaun');
    $this->get('/akaun')->assertOk();
});

it('rate limits gate attempts', function () {
    $child = childWithPass();
    $child->user->forceFill(['parent_pin' => '4821'])->save();
    enterChildMode($child);

    foreach (range(1, 10) as $i) {
        $this->post('/mod-anak/keluar', ['answer' => '0000']);
    }

    $this->post('/mod-anak/keluar', ['answer' => '4821'])->assertStatus(429);
});

it('writes numbers in Malay words', function (int $n, string $words) {
    expect(ParentGate::words($n))->toBe($words);
})->with([
    [3, 'tiga'], [10, 'sepuluh'], [11, 'sebelas'], [17, 'tujuh belas'],
    [20, 'dua puluh'], [23, 'dua puluh tiga'], [39, 'tiga puluh sembilan'],
]);

// --- Setting the PIN -------------------------------------------------------------

it('sets, hashes and removes the parent PIN with the account password', function () {
    $parent = User::factory()->create(['password' => 'KataLaluan2026']);
    $this->actingAs($parent);

    $this->put('/akaun/pin', ['pin' => '4821', 'pin_confirmation' => '4821', 'current_password' => 'salah'])
        ->assertSessionHasErrorsIn('parentPin', 'current_password');
    $this->put('/akaun/pin', ['pin' => '12a4', 'pin_confirmation' => '12a4', 'current_password' => 'KataLaluan2026'])
        ->assertSessionHasErrorsIn('parentPin', 'pin');

    $this->put('/akaun/pin', ['pin' => '4821', 'pin_confirmation' => '4821', 'current_password' => 'KataLaluan2026'])
        ->assertRedirect('/akaun');
    expect($parent->refresh()->parent_pin)->not->toBe('4821')
        ->and(Hash::check('4821', $parent->parent_pin))->toBeTrue();

    $this->delete('/akaun/pin', ['current_password' => 'KataLaluan2026'])->assertRedirect('/akaun');
    expect($parent->refresh()->hasParentPin())->toBeFalse();
});
