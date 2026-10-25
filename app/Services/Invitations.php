<?php

namespace App\Services;

use App\Enums\Role;
use App\Mail\InstitutionInvitation;
use App\Models\Institution;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * E-mail invitations into an institution. Only a hash of the token is kept, so
 * a database leak does not hand out working links.
 */
class Invitations
{
    public function invite(Institution $institution, string $email, string $name, Role $role, ?User $inviter): Invitation
    {
        if (! $role->isInstitutionStaff()) {
            throw new InvalidArgumentException('Jemputan hanya untuk pentadbir institusi atau guru.');
        }

        $token = Str::random(48);

        // A new invitation replaces any unused one for the same address.
        $institution->invitations()->where('email', $email)->whereNull('accepted_at')->delete();

        $invitation = $institution->invitations()->create([
            'email' => Str::lower($email),
            'name' => $name,
            'role' => $role,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $inviter?->id,
            'expires_at' => now()->addDays(Invitation::VALID_DAYS),
        ]);

        Mail::to($invitation->email)->queue(new InstitutionInvitation($invitation, $token));

        return $invitation;
    }

    /** Creates the account; the e-mail counts as verified since the link reached it. */
    public function accept(Invitation $invitation, string $name, string $password): User
    {
        return DB::transaction(function () use ($invitation, $name, $password) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
            ]);
            $user->forceFill([
                'role' => $invitation->role,
                'institution_id' => $invitation->institution_id,
                'email_verified_at' => now(),
                'weekly_report' => false,
            ])->save();

            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
    }
}
