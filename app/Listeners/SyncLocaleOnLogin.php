<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Locale;
use Illuminate\Auth\Events\Login;

/**
 * Keeps the display language and the account's e-mail language the same.
 * A language picked before logging in (BM | EN switch) is saved to the
 * account; with no pick in this browser, the account's language is shown.
 */
class SyncLocaleOnLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;
        $request = request();

        if (! $user instanceof User || ! $request->hasSession()) {
            return;
        }

        $chosen = $request->session()->get(Locale::SESSION_KEY) ?? $request->cookie(Locale::COOKIE);

        if (Locale::isSupported($chosen)) {
            if ($user->locale !== $chosen) {
                $user->forceFill(['locale' => $chosen])->save();
            }
        } elseif (Locale::isSupported($user->locale)) {
            $request->session()->put(Locale::SESSION_KEY, $user->locale);
        }
    }
}
