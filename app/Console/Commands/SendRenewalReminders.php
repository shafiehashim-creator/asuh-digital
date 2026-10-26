<?php

namespace App\Console\Commands;

use App\Mail\RenewalReminder;
use App\Models\Pass;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('asuh:renewal-reminders')]
#[Description('Remind families 3 days before a monthly subscription ends (it is paid by hand each month)')]
class SendRenewalReminders extends Command
{
    public const DAYS_BEFORE = 3;

    public function handle(): int
    {
        $sent = 0;

        Pass::query()
            ->whereNull('renewal_reminded_at')
            ->whereNotNull('starts_at')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', now()->addDays(self::DAYS_BEFORE))
            ->whereHas('plan', fn ($query) => $query->where('duration_months', 1))
            ->with(['plan', 'user'])
            ->get()
            ->each(function (Pass $pass) use (&$sent) {
                // Already renewed (another subscription runs on past this one): nothing to remind.
                $renewed = Pass::query()->where('user_id', $pass->user_id)->where('ends_at', '>', $pass->ends_at)->exists();

                if (! $renewed && $pass->user) {
                    Mail::to($pass->user)->queue(new RenewalReminder($pass));
                    $sent++;
                }

                $pass->forceFill(['renewal_reminded_at' => now()])->save();
            });

        $this->info("{$sent} peringatan pembaharuan dihantar ke baris gilir.");

        return self::SUCCESS;
    }
}
