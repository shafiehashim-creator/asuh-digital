<?php

use App\Enums\Role;
use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Illuminate\Support\Facades\Hash;

it('renders the admin login page', function () {
    $this->get('/admin/login')->assertOk();
});

it('redirects guests away from the admin dashboard', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets a super admin into the admin panel', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk();
});

it('blocks parents and institution admins from the admin panel', function (Role $role) {
    $this->actingAs(User::factory()->role($role)->create())
        ->get('/admin')
        ->assertKeptOut();
})->with([Role::Parent, Role::InstitutionAdmin]);

it('gives new users the parent role by default', function () {
    $user = User::query()->create([
        'name' => 'Ibu Contoh',
        'email' => 'ibu@example.com',
        'password' => 'rahsia-sekali-123',
    ])->refresh();

    expect($user->role)->toBe(Role::Parent);
});

it('does not allow the role to be mass assigned', function () {
    $user = User::query()->create([
        'name' => 'Penceroboh',
        'email' => 'penceroboh@example.com',
        'password' => 'rahsia-sekali-123',
        'role' => 'admin',
    ])->refresh();

    expect($user->role)->toBe(Role::Parent);
});

it('lets the admin change their own password from the panel profile', function () {
    $admin = User::factory()->admin()->create(['password' => 'LamaKataLaluan2026']);
    $this->actingAs($admin);

    $this->get('/admin/profile')->assertOk();

    Livewire\Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => 'BaharuKataLaluan2026',
            'passwordConfirmation' => 'BaharuKataLaluan2026',
            'currentPassword' => 'LamaKataLaluan2026',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('BaharuKataLaluan2026', $admin->fresh()->password))->toBeTrue();
});

it('keeps parents out of the admin profile page', function () {
    $this->actingAs(User::factory()->create())->get('/admin/profile')->assertKeptOut();
});
