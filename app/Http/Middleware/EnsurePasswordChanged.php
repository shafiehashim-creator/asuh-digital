<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A staff member signed in with the temporary password a Super Admin gave
 * them sets their own on the panel's profile page before anything else.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! $request->routeIs('filament.admin.auth.profile', 'filament.admin.auth.logout', 'livewire.*')) {
            Notification::make()->title('Sila tetapkan kata laluan anda sendiri dahulu.')
                ->body('Anda log masuk dengan kata laluan sementara daripada Super Admin.')->warning()->send();

            return redirect()->route('filament.admin.auth.profile');
        }

        return $next($request);
    }
}
