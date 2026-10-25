<?php

use App\Models\User;

it('offers to switch account when a signed-in user opens the login page', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin ASUH']);

    $this->actingAs($admin)->get('/login')->assertRedirect(route('switch-account'));

    $this->actingAs($admin)->get(route('switch-account'))
        ->assertOk()
        ->assertSee('Admin ASUH')
        ->assertSee('Log keluar dan log masuk akaun lain')
        ->assertSee(url('/admin'));
});

it('sends a parent opening the admin panel to switch account instead of a 403', function () {
    $parent = User::factory()->create();

    $this->actingAs($parent)->get('/admin')->assertRedirect(route('switch-account', ['ke' => 'admin']));

    $this->actingAs($parent)->get(route('switch-account', ['ke' => 'admin']))
        ->assertOk()
        ->assertSee('Akaun ini tidak boleh membuka panel admin.');
});

it('still lets staff into the admin panel', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
});

it('sends a parent opening the teacher portal to switch account', function () {
    $this->actingAs(User::factory()->create())->get('/guru')
        ->assertRedirect(route('switch-account', ['ke' => 'guru']));
});

it('logs out and returns to the matching login page', function () {
    $parent = User::factory()->create();

    $this->actingAs($parent)->post(route('switch-account.logout'), ['ke' => 'admin'])
        ->assertRedirect(url('/admin/login'));
    $this->assertGuest();

    $this->actingAs($parent)->post(route('switch-account.logout'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('ignores unknown targets so it is never an open redirect', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('switch-account.logout'), ['ke' => 'https://contoh.com'])
        ->assertRedirect(route('login'));
});
