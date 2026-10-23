<?php

use App\Enums\Role;
use App\Models\User;

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
        ->assertForbidden();
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
