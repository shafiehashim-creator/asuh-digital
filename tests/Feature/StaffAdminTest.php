<?php

use App\Enums\Role;
use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create(['name' => 'Ketua']);
});

it('lists staff for Super Admins only', function () {
    User::factory()->role(Role::Editor)->create(['name' => 'Editor Satu']);
    User::factory()->create(['name' => 'Ibu Bukan Staf']);

    $this->actingAs($this->admin)->get('/admin/staf')
        ->assertOk()->assertSee('Editor Satu')->assertSee('Ketua')->assertDontSee('Ibu Bukan Staf');

    $this->actingAs(User::factory()->role(Role::Editor)->create())->get('/admin/staf')->assertForbidden();
});

it('adds a new staff member who gets an e-mail to set a password', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManageStaff::class)
        ->callAction('addStaff', data: ['name' => 'Editor Baru', 'email' => 'Editor@Asuh.test', 'role' => 'editor', 'access' => 'email'])
        ->assertHasNoActionErrors();

    $staff = User::query()->where('email', 'editor@asuh.test')->sole();
    expect($staff->role)->toBe(Role::Editor)->and($staff->hasVerifiedEmail())->toBeTrue();
    Notification::assertSentTo($staff, ResetPassword::class);
});

it('gives an existing customer account the role without a new account or e-mail', function () {
    $customer = User::factory()->create(['email' => 'ibu@contoh.my']);
    $this->actingAs($this->admin);

    Livewire::test(ManageStaff::class)
        ->callAction('addStaff', data: ['name' => 'Ibu', 'email' => 'ibu@contoh.my', 'role' => 'admin', 'access' => 'keep']);

    expect($customer->fresh()->role)->toBe(Role::Admin);
    Notification::assertNothingSent();
});

it('changes roles and removes access, but never the last Super Admin or yourself', function () {
    $editor = User::factory()->role(Role::Editor)->create();
    $this->actingAs($this->admin);

    Livewire::test(ManageStaff::class)->callTableAction('changeRole', $editor, data: ['role' => 'admin']);
    expect($editor->fresh()->role)->toBe(Role::Admin);

    Livewire::test(ManageStaff::class)->callTableAction('removeAccess', $editor);
    expect($editor->fresh()->role)->toBe(Role::Parent);
    $this->actingAs($editor->fresh())->get('/admin')->assertRedirect(route('switch-account', ['ke' => 'admin']));

    $this->actingAs($this->admin);
    Livewire::test(ManageStaff::class)->assertTableActionHidden('removeAccess', $this->admin);
});

it('lets one Super Admin remove another, but not themselves', function () {
    $other = User::factory()->admin()->create();
    $this->actingAs($other);

    Livewire::test(ManageStaff::class)->callTableAction('removeAccess', $this->admin);
    expect($this->admin->fresh()->role)->toBe(Role::Parent);

    // $other is now the only Super Admin and cannot remove or demote themselves.
    Livewire::test(ManageStaff::class)
        ->assertTableActionHidden('removeAccess', $other)
        ->assertTableActionHidden('changeRole', $other);
});

it('links the public site from the admin panel', function () {
    $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('Lihat laman');
});

it('adds staff with a temporary password, which must be changed at first login', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManageStaff::class)
        ->callAction('addStaff', data: ['name' => 'Kak Ros', 'email' => 'ros@asuh.test', 'role' => 'editor', 'access' => 'password', 'temporary_password' => 'Asuh-abcd-2345'])
        ->assertHasNoActionErrors()
        ->assertNotified('Beri maklumat log masuk ini kepada Kak Ros');

    $staff = User::query()->where('email', 'ros@asuh.test')->sole();
    expect($staff->must_change_password)->toBeTrue()
        ->and(Hash::check('Asuh-abcd-2345', $staff->password))->toBeTrue();
    Notification::assertNothingSent();

    // Every panel page sends them to the profile page until they set their own password.
    $this->actingAs($staff)->get('/admin')->assertRedirect(route('filament.admin.auth.profile'));
    $this->get(route('filament.admin.auth.profile'))->assertOk();

    $staff->forceFill(['password' => 'KataLaluanSendiri123'])->save();
    expect($staff->fresh()->must_change_password)->toBeFalse();
    // A new session, as after signing in again with the new password.
    $this->flushSession();
    $this->actingAs($staff->fresh())->get('/admin')->assertOk();
});

it('gives staff whose access was removed a new password when added again', function () {
    $editor = User::factory()->role(Role::Editor)->create(['email' => 'editor@asuh.test']);
    $this->actingAs($this->admin);
    Livewire::test(ManageStaff::class)->callTableAction('removeAccess', $editor);

    Livewire::test(ManageStaff::class)
        ->callAction('addStaff', data: ['name' => 'Editor', 'email' => 'editor@asuh.test', 'role' => 'editor', 'access' => 'password', 'temporary_password' => 'Asuh-wxyz-6789']);

    $editor->refresh();
    expect($editor->role)->toBe(Role::Editor)->and($editor->must_change_password)->toBeTrue()
        ->and(Hash::check('Asuh-wxyz-6789', $editor->password))->toBeTrue();
});

it('sends a new password e-mail or sets a temporary password for existing staff', function () {
    $editor = User::factory()->role(Role::Editor)->create();
    $this->actingAs($this->admin);

    Livewire::test(ManageStaff::class)->callTableAction('resetPassword', $editor, data: ['access' => 'email']);
    Notification::assertSentTo($editor, ResetPassword::class);

    Livewire::test(ManageStaff::class)->callTableAction('resetPassword', $editor, data: ['access' => 'password', 'temporary_password' => 'Asuh-mnpq-3456']);
    expect($editor->fresh()->must_change_password)->toBeTrue();

    Livewire::test(ManageStaff::class)->assertTableActionHidden('resetPassword', $this->admin);
    expect(config('auth.passwords.users.expire'))->toBe(1440)
        ->and(StaffResource::temporaryPassword())->toMatch('/^Asuh-[a-z]{4}-[2-9]{4}$/');
});
