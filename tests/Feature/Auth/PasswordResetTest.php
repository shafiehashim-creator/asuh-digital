<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('sends a Malay reset link to a registered e-mail', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        return $notification->toMail($user)->subject === 'Tetapkan semula kata laluan';
    });
});

it('does not reveal whether an e-mail is registered', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);
    $known = session('status');

    $this->post('/forgot-password', ['email' => 'tiada@example.com'])
        ->assertSessionHasErrors(['email' => $known]);

    Notification::assertCount(1);
});

it('resets the password with a valid token', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email))
            ->assertOk()
            ->assertSee('Tetapkan kata laluan baharu');

        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'baharu2026',
            'password_confirmation' => 'baharu2026',
        ])->assertRedirect('/login');

        return true;
    });

    expect(Hash::check('baharu2026', $user->fresh()->password))->toBeTrue();
});

it('requires letters and numbers in a new password', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'hanyahuruf',
            'password_confirmation' => 'hanyahuruf',
        ])->assertSessionHasErrors('password');

        return true;
    });
});
