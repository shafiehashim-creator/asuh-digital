<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Filament's login check, but a signed-in account that may not use the panel
 * (a parent or teacher) is offered "Tukar akaun" instead of a bare 403.
 */
class AuthenticateAdmin extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();

        if ($user && ! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            throw new HttpResponseException(redirect()->route('switch-account', ['ke' => 'admin']));
        }

        parent::authenticate($request, $guards);
    }
}
