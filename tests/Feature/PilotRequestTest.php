<?php

use App\Enums\InstitutionType;
use App\Enums\PilotStatus;
use App\Enums\Role;
use App\Filament\Resources\PilotRequests\Pages\ListPilotRequests;
use App\Filament\Resources\PilotRequests\Pages\ViewPilotRequest;
use App\Mail\PilotRequestConfirmation;
use App\Mail\PilotRequestReceived;
use App\Models\PilotRequest;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(fn () => Mail::fake());

function pilotForm(array $overrides = []): array
{
    return array_merge([
        'institution_name' => 'Tadika Al-Amin',
        'institution_type' => 'tadika',
        'state' => 'Selangor',
        'district' => 'Gombak',
        'contact_name' => 'Ustazah Aminah',
        'contact_position' => 'Guru Besar',
        'email' => 'alamin@example.com',
        'phone' => '03-6123 4567',
        'student_count' => 120,
        'age_group' => '6-9',
        'notes' => 'Hubungi waktu pagi',
        'pdpa_consent' => '1',
    ], $overrides);
}

it('links to the pilot form from the landing page', function () {
    $this->seed(PlanSeeder::class);

    $this->get('/')->assertSee(route('pilot.create'), false)->assertSee('Mohon pilot percuma');
});

it('shows the pilot form with the pilot length from settings', function () {
    Setting::set('pilot_duration_days', 45);

    $this->get('/pilot')->assertOk()->assertSee('Mohon pilot percuma')->assertSee('45 hari');
});

it('saves a pilot request as new and thanks the institution', function () {
    $this->post('/pilot', pilotForm())->assertRedirect(route('pilot.thanks'));

    $pilot = PilotRequest::query()->sole();

    expect($pilot->institution_name)->toBe('Tadika Al-Amin')
        ->and($pilot->institution_type)->toBe(InstitutionType::Kindergarten)
        ->and($pilot->status)->toBe(PilotStatus::New)
        ->and($pilot->phone)->toBe('0361234567')
        ->and($pilot->student_count)->toBe(120);

    $this->get(route('pilot.thanks'))->assertSee('Tadika Al-Amin');
});

it('accepts mobile and office numbers but rejects junk', function (string $phone, bool $valid) {
    $response = $this->post('/pilot', pilotForm(['phone' => $phone]));

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('phone');
})->with([
    'mobile' => ['012-345 6789', true],
    'office' => ['03-6123 4567', true],
    'with +60' => ['+60 3-6123 4567', true],
    'too short' => ['12345', false],
    'letters' => ['telefon saya', false],
]);

it('shows every validation error at once and requires consent', function () {
    $this->post('/pilot', [])->assertSessionHasErrors([
        'institution_name', 'institution_type', 'state', 'district', 'contact_name',
        'contact_position', 'email', 'phone', 'student_count', 'age_group', 'pdpa_consent',
    ]);

    expect(PilotRequest::query()->count())->toBe(0);
});

it('quietly turns away bots that fill in the hidden field', function () {
    $this->post('/pilot', pilotForm(['website' => 'http://spam.example']))->assertSessionHasErrors('website');

    expect(PilotRequest::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('rate limits pilot submissions', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/pilot', []);
    }

    $this->post('/pilot', [])->assertStatus(429);
});

it('e-mails a copy to the institution and notifies the admins', function () {
    Setting::set('admin_notification_emails', ['jualan@example.com']);

    $this->post('/pilot', pilotForm());

    Mail::assertQueued(PilotRequestConfirmation::class, fn ($mail) => $mail->hasTo('alamin@example.com'));
    Mail::assertQueued(PilotRequestReceived::class, fn ($mail) => $mail->hasTo('jualan@example.com')
        && $mail->hasReplyTo('alamin@example.com'));
});

it('still saves the request when no admin e-mail is set', function () {
    $this->post('/pilot', pilotForm())->assertRedirect();

    expect(PilotRequest::query()->count())->toBe(1);
    Mail::assertNotQueued(PilotRequestReceived::class);
});

it('does not let form input inject links into the admin e-mail', function () {
    $pilot = PilotRequest::factory()->create([
        'institution_name' => 'Tadika [Klik di sini](http://laman-palsu.example)',
        'notes' => '![x](http://penjejak.example/a.png)',
    ]);

    $html = (new PilotRequestReceived($pilot))->render();

    expect($html)->not->toContain('href="http://laman-palsu.example"')
        ->and($html)->not->toContain('<img src="http://penjejak.example')
        ->and($html)->toContain('Klik di sini');
});

// --- Admin panel -------------------------------------------------------------

it('lists pilot requests for the admin and filters by status', function () {
    $this->actingAs(User::factory()->admin()->create());
    $new = PilotRequest::factory()->create(['status' => PilotStatus::New]);
    $paying = PilotRequest::factory()->create(['status' => PilotStatus::Paying]);

    Livewire::test(ListPilotRequests::class)
        ->assertCanSeeTableRecords([$new, $paying])
        ->filterTable('status', PilotStatus::Paying->value)
        ->assertCanSeeTableRecords([$paying])
        ->assertCanNotSeeTableRecords([$new]);
});

it('lets the admin move a request through its statuses', function () {
    $this->actingAs(User::factory()->admin()->create());
    $pilot = PilotRequest::factory()->create();

    Livewire::test(ViewPilotRequest::class, ['record' => $pilot->getRouteKey()])
        ->callAction('changeStatus', data: ['status' => PilotStatus::Contacted->value])
        ->assertHasNoActionErrors();

    expect($pilot->fresh()->status)->toBe(PilotStatus::Contacted);
});

it('keeps editors and parents out of pilot requests', function (Role $role) {
    $this->actingAs(User::factory()->role($role)->create())
        ->get('/admin/permohonan-pilot')
        ->assertKeptOut();
})->with([Role::Editor, Role::Parent]);
