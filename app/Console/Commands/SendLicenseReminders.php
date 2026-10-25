<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Mail\LicenseEndingSoon;
use App\Models\License;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('asuh:license-reminders')]
#[Description('Remind institution administrators 30 and 7 days before their licence ends')]
class SendLicenseReminders extends Command
{
    /** Days before the end on which a reminder goes out; runs once a day. */
    public const DAYS_BEFORE = [30, 7];

    public function handle(): int
    {
        $sent = 0;
        $dates = array_map(fn (int $days) => today()->addDays($days)->toDateString(), self::DAYS_BEFORE);

        License::query()
            ->where(function ($query) use ($dates) {
                foreach ($dates as $date) {
                    $query->orWhereDate('ends_on', $date);
                }
            })
            ->with('institution.users')->get()
            ->each(function (License $license) use (&$sent) {
                // A licence already renewed (another one running past this one) needs no reminder.
                $renewed = $license->institution->licenses()->whereDate('ends_on', '>', $license->ends_on)->exists();
                $admins = $license->institution->users->where('role', Role::InstitutionAdmin);

                if ($renewed || $admins->isEmpty()) {
                    return;
                }

                Mail::to($admins)->queue(new LicenseEndingSoon($license));
                $sent++;
            });

        $this->info("{$sent} peringatan lesen dihantar ke baris gilir.");

        return self::SUCCESS;
    }
}
