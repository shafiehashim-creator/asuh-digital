<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

function newParent(array $overrides = []): array
{
    return array_merge([
        'name' => 'Puan Aminah',
        'email' => 'aminah@example.com',
        'phone' => '012-345 6789',
        'password' => 'KataLaluan2026',
        'password_confirmation' => 'KataLaluan2026',
        'pdpa_consent' => '1',
    ], $overrides);
}

it('shows the sign-up page in Malay, linked from the login page', function () {
    $this->get('/login')->assertOk()->assertSee('Daftar percuma')->assertSee(route('register'), false);
    $this->get('/daftar')->assertOk()->assertSee('Daftar akaun')->assertSee('notis privasi');
});

it('creates a parent account, signs in and asks to verify the e-mail', function () {
    Notification::fake();

    $this->post('/daftar', newParent() + ['role' => 'admin'])->assertRedirect('/akaun');

    $user = User::query()->where('email', 'aminah@example.com')->sole();
    expect($user->refresh()->role)->toBe(Role::Parent)
        ->and($user->phone)->not->toBeEmpty()
        ->and($user->hasVerifiedEmail())->toBeFalse();
    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);

    // The account page waits for the e-mail to be verified.
    $this->get('/akaun')->assertRedirect('/email/verify');
});

it('needs consent and valid details', function (array $overrides, string $field) {
    $this->post('/daftar', newParent($overrides))->assertSessionHasErrors($field);
    expect(User::query()->count())->toBe(0);
})->with([
    'no consent' => [['pdpa_consent' => null], 'pdpa_consent'],
    'bad phone' => [['phone' => '123'], 'phone'],
    'short password' => [['password' => 'abc', 'password_confirmation' => 'abc'], 'password'],
    'no name' => [['name' => ''], 'name'],
]);

it('refuses an e-mail that already has an account', function () {
    User::factory()->create(['email' => 'aminah@example.com']);

    $this->post('/daftar', newParent())->assertSessionHasErrors('email');
});

it('limits sign-ups from one address', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/daftar', newParent(['email' => "ibu{$i}@example.com"]));
        auth()->logout();
    }

    $this->post('/daftar', newParent(['email' => 'ibu6@example.com']))->assertStatus(429);
    expect(User::query()->count())->toBe(5);
});

it('sends signed-in users away from the sign-up page', function () {
    $this->actingAs(User::factory()->create())->get('/daftar')->assertRedirect();
});
