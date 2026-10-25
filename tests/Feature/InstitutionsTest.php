<?php

use App\Enums\LicenseKind;
use App\Enums\PilotStatus;
use App\Enums\Role;
use App\Filament\Resources\Institutions\Pages\EditInstitution;
use App\Filament\Resources\Institutions\RelationManagers\LicensesRelationManager;
use App\Filament\Resources\PilotRequests\Pages\ViewPilotRequest;
use App\Mail\InstitutionInvitation;
use App\Models\Institution;
use App\Models\Invitation;
use App\Models\License;
use App\Models\PilotRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Invitations;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(fn () => Mail::fake());

/** Sends an invitation and returns the token from the e-mail. */
function inviteToInstitution(Institution $institution, string $email = 'guru.besar@tadika.my', Role $role = Role::InstitutionAdmin): string
{
    app(Invitations::class)->invite($institution, $email, 'Ustazah Halimah', $role, null);
    $token = null;
    Mail::assertQueued(InstitutionInvitation::class, function (InstitutionInvitation $mail) use (&$token, $email) {
        $token = $mail->token;

        return $mail->hasTo($email);
    });

    return $token;
}

// --- Licences ------------------------------------------------------------------

it('knows which licence is active today', function () {
    $institution = Institution::factory()->create();
    License::factory()->for($institution)->create(['starts_on' => today()->subYears(2), 'ends_on' => today()->subYear()]);
    expect($institution->activeLicense())->toBeNull();

    $current = License::factory()->for($institution)->create(['starts_on' => today(), 'ends_on' => today()]);
    expect($institution->activeLicense()?->id)->toBe($current->id)
        ->and($current->isActive())->toBeTrue();

    $this->travel(1)->days();
    expect($institution->fresh()->activeLicense())->toBeNull();
});

it('lets the super admin create institutions and add licences', function () {
    $this->actingAs(User::factory()->admin()->create());
    $institution = Institution::factory()->create();

    $this->get('/admin/institusi')->assertOk()->assertSee($institution->name);
    $this->get("/admin/institusi/{$institution->id}/ubah")->assertOk();

    Livewire::test(LicensesRelationManager::class, ['ownerRecord' => $institution, 'pageClass' => EditInstitution::class])
        ->callTableAction('create', data: [
            'kind' => LicenseKind::Paid->value, 'max_students' => 120,
            'starts_on' => today()->toDateString(), 'ends_on' => today()->addYear()->subDay()->toDateString(),
            'notes' => 'INV-2026-001',
        ])
        ->assertHasNoTableActionErrors();

    expect($institution->activeLicense()->max_students)->toBe(120);
});

it('keeps institutions away from editors', function () {
    $this->actingAs(User::factory()->role(Role::Editor)->create())->get('/admin/institusi')->assertForbidden();
});

it('starts a pilot from a pilot request', function () {
    Setting::set('pilot_duration_days', 30);
    $this->actingAs(User::factory()->admin()->create());
    $pilot = PilotRequest::factory()->create(['institution_name' => 'KAFA Al-Amin', 'student_count' => 45]);

    Livewire::test(ViewPilotRequest::class, ['record' => $pilot->id])->callAction('createInstitution');

    $institution = Institution::query()->sole();
    $license = $institution->activeLicense();
    expect($institution->name)->toBe('KAFA Al-Amin')
        ->and($license->kind)->toBe(LicenseKind::Pilot)
        ->and($license->max_students)->toBe(45)
        ->and($license->ends_on->toDateString())->toBe(today()->addDays(29)->toDateString())
        ->and($pilot->fresh()->status)->toBe(PilotStatus::PilotActive);
});

it('invites the institution administrator from the admin panel', function () {
    $this->actingAs(User::factory()->admin()->create());
    $institution = Institution::factory()->create();

    Livewire::test(EditInstitution::class, ['record' => $institution->id])
        ->callAction('inviteAdmin', data: ['name' => 'Ustazah Halimah', 'email' => 'guru.besar@tadika.my'])
        ->assertHasNoActionErrors();

    $invitation = Invitation::query()->sole();
    expect($invitation->role)->toBe(Role::InstitutionAdmin)
        ->and($invitation->token_hash)->toHaveLength(64);
    Mail::assertQueued(InstitutionInvitation::class);
});

it('refuses to invite an e-mail that already has an account', function () {
    $this->actingAs(User::factory()->admin()->create());
    $institution = Institution::factory()->create();
    User::factory()->create(['email' => 'ibu@contoh.my']);

    Livewire::test(EditInstitution::class, ['record' => $institution->id])
        ->callAction('inviteAdmin', data: ['name' => 'Ibu', 'email' => 'ibu@contoh.my'])
        ->assertHasActionErrors(['email']);
});

// --- Accepting -----------------------------------------------------------------

it('creates a verified institution account from the invitation and opens the teacher portal', function () {
    $institution = Institution::factory()->create(['name' => 'Tadika Seri Ilmu']);
    License::factory()->for($institution)->create(['max_students' => 40]);
    $token = inviteToInstitution($institution);

    $this->get("/jemputan/{$token}")->assertOk()->assertSee('Sertai Tadika Seri Ilmu')->assertSee('guru.besar@tadika.my');

    $this->post("/jemputan/{$token}", [
        'name' => 'Ustazah Halimah', 'password' => 'KataLaluan2026', 'password_confirmation' => 'KataLaluan2026', 'terms' => '1',
        'role' => 'admin', // ignored
    ])->assertRedirect('/guru');

    $user = User::query()->where('email', 'guru.besar@tadika.my')->sole();
    expect($user->role)->toBe(Role::InstitutionAdmin)
        ->and($user->institution_id)->toBe($institution->id)
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Invitation::query()->sole()->accepted_at)->not->toBeNull();

    $this->get('/guru')->assertOk()->assertSee('Tadika Seri Ilmu')->assertSee('untuk sehingga 40 murid');
    $this->get('/akaun')->assertRedirect('/guru');
});

it('does not reuse, accept late or guess invitations', function () {
    $institution = Institution::factory()->create();
    $token = inviteToInstitution($institution);
    $data = ['name' => 'Guru', 'password' => 'KataLaluan2026', 'password_confirmation' => 'KataLaluan2026', 'terms' => '1'];

    $this->get('/jemputan/'.str_repeat('x', 48))->assertOk()->assertSee('Jemputan tidak sah');
    $this->post('/jemputan/'.str_repeat('x', 48), $data)->assertNotFound();

    $this->travel(8)->days();
    $this->get("/jemputan/{$token}")->assertSee('Jemputan tidak sah');
    $this->post("/jemputan/{$token}", $data)->assertNotFound();
    $this->travelBack();

    $this->post("/jemputan/{$token}", $data)->assertRedirect('/guru');
    auth()->logout();
    $this->post("/jemputan/{$token}", $data)->assertNotFound();
});

it('replaces an earlier unused invitation to the same address', function () {
    $institution = Institution::factory()->create();
    $first = inviteToInstitution($institution);
    inviteToInstitution($institution);

    expect(Invitation::query()->count())->toBe(1);
    $this->get("/jemputan/{$first}")->assertSee('Jemputan tidak sah');
});

it('keeps parents and admins out of the teacher portal', function () {
    $this->actingAs(User::factory()->create())->get('/guru')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get('/guru')->assertForbidden();
    // A teacher role without an institution is not enough either.
    $this->actingAs(User::factory()->role(Role::Teacher)->create())->get('/guru')->assertForbidden();
});

it('tells the teacher when there is no active licence', function () {
    $institution = Institution::factory()->create();
    $teacher = User::factory()->role(Role::Teacher)->create();
    $teacher->forceFill(['institution_id' => $institution->id])->save();

    $this->actingAs($teacher)->get('/guru')->assertOk()->assertSee('Tiada lesen aktif');
});
