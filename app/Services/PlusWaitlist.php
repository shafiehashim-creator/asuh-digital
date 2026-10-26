<?php

namespace App\Services;

use App\Mail\PlusNowOpen;
use App\Models\PlusWaitlistEntry;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * "Beritahu saya bila dibuka" for Langganan Plus: sign-ups while Plus is
 * shown but not sold, and one e-mail to each when the admin opens sales.
 */
class PlusWaitlist
{
    public function join(string $email, ?User $user): PlusWaitlistEntry
    {
        $entry = PlusWaitlistEntry::firstOrNew(['email' => mb_strtolower(trim($email))]);
        $entry->user_id ??= $user?->id;
        $entry->locale = app()->getLocale();
        $entry->save();

        return $entry;
    }

    public function waitingCount(): int
    {
        return PlusWaitlistEntry::whereNull('notified_at')->count();
    }

    /** Queue the opening e-mail to everyone not told yet; returns how many. */
    public function notifyAll(): int
    {
        $sent = 0;

        PlusWaitlistEntry::whereNull('notified_at')->chunkById(200, function ($entries) use (&$sent) {
            foreach ($entries as $entry) {
                Mail::to($entry->email)->locale($entry->locale)->queue(new PlusNowOpen);
                $entry->forceFill(['notified_at' => now()])->save();
                $sent++;
            }
        });

        return $sent;
    }
}
