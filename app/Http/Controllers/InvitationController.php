<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Services\Invitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Accepting an institution invitation: set a name and password, then into /guru. */
class InvitationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = Invitation::findByToken($token);

        return view('invitation.show', [
            'invitation' => $invitation?->isUsable() ? $invitation->load('institution') : null,
            'taken' => $invitation && User::query()->where('email', $invitation->email)->exists(),
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token, Invitations $invitations): RedirectResponse
    {
        $invitation = Invitation::findByToken($token);
        abort_unless($invitation?->isUsable(), 404);

        if (User::query()->where('email', $invitation->email)->exists()) {
            return back()->withErrors(['email' => 'E-mel ini sudah mempunyai akaun ASUH Digital. Hubungi kami untuk menyambungkannya ke institusi anda.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ], ['terms.accepted' => 'Sila setuju dengan terma dan notis privasi.'], ['name' => 'nama', 'password' => 'kata laluan']);

        $user = $invitations->accept($invitation, $data['name'], $data['password']);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('teacher.home');
    }
}
