<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a verified super admin', function () {
    $this->artisan('asuh:create-admin')
        ->expectsQuestion('Nama', 'Shafie')
        ->expectsQuestion('E-mel', 'admin@example.com')
        ->expectsQuestion('Kata laluan (sekurang-kurangnya 12 aksara)', 'KataLaluan2026')
        ->expectsQuestion('Taip semula kata laluan', 'KataLaluan2026')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('KataLaluan2026', $admin->password))->toBeTrue();
});

it('refuses a weak or mismatched password', function (string $password, string $confirmation) {
    $this->artisan('asuh:create-admin')
        ->expectsQuestion('Nama', 'Shafie')
        ->expectsQuestion('E-mel', 'admin@example.com')
        ->expectsQuestion('Kata laluan (sekurang-kurangnya 12 aksara)', $password)
        ->expectsQuestion('Taip semula kata laluan', $confirmation)
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
})->with([
    'too short' => ['Pendek1', 'Pendek1'],
    'mismatch' => ['KataLaluan2026', 'KataLaluan2027'],
]);

it('refuses an e-mail that is already registered', function () {
    User::factory()->create(['email' => 'admin@example.com']);

    $this->artisan('asuh:create-admin')
        ->expectsQuestion('Nama', 'Shafie')
        ->expectsQuestion('E-mel', 'admin@example.com')
        ->expectsQuestion('Kata laluan (sekurang-kurangnya 12 aksara)', 'KataLaluan2026')
        ->expectsQuestion('Taip semula kata laluan', 'KataLaluan2026')
        ->assertFailed();
});
