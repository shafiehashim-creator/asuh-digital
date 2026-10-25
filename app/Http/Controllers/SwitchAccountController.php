<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * One browser holds one login. When a signed-in user opens a login page, or a
 * page their account may not use (a parent opening /admin), they land here
 * and can log out to sign in with another account, instead of seeing a 403.
 */
class SwitchAccountController extends Controller
{
    /** Where the visitor was heading; only these values are accepted, so this is never an open redirect. */
    private const TARGETS = ['admin', 'guru'];

    public function show(Request $request): View
    {
        $user = $request->user();
        $target = $this->target($request);

        return view('auth.switch-account', [
            'user' => $user,
            'target' => $target,
            'blocked' => $target !== null && ! $this->canUse($request, $target),
            'continueUrl' => $user->isStaff() ? url('/admin') : route('account'),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $target = $this->target($request);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($target === 'admin' ? url('/admin/login') : route('login'));
    }

    private function target(Request $request): ?string
    {
        $target = $request->input('ke');

        return in_array($target, self::TARGETS, true) ? $target : null;
    }

    private function canUse(Request $request, string $target): bool
    {
        return match ($target) {
            'admin' => $request->user()->isStaff(),
            'guru' => $request->user()->isInstitutionStaff(),
        };
    }
}
