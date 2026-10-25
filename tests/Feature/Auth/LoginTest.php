<?php

use App\Models\User;

it('shows the login page in Malay', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Log masuk')
        ->assertSee('Lupa kata laluan?');
});

it('logs a parent in and sends them to their account page', function () {
    $user = User::factory()->create(['password' => 'rahsia123']);

    $this->post('/login', ['email' => $user->email, 'password' => 'rahsia123'])
        ->assertRedirect('/akaun');

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password with a Malay message', function () {
    $user = User::factory()->create(['password' => 'rahsia123']);

    $this->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => 'salah999'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'E-mel atau kata laluan tidak betul.']);

    $this->assertGuest();
});

it('locks out login after five failed attempts', function () {
    $user = User::factory()->create(['password' => 'rahsia123']);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'salah999']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'rahsia123'])
        ->assertStatus(429);

    $this->assertGuest();
});

it('offers registration at the Malay address only', function () {
    $this->get('/register')->assertNotFound();
    $this->get('/daftar')->assertOk();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});
