<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Mail\WeeklyReadingReport;
use App\Models\Setting;
use App\Models\User;
use App\Services\WeeklyReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('asuh:weekly-reports')]
#[Description('E-mail parents what their children read last week')]
class SendWeeklyReports extends Command
{
    /**
     * Runs every Monday morning. Only parents who asked for it and whose
     * children read last week get one, and never twice for the same week,
     * so running it again is harmless.
     */
    public function handle(WeeklyReport $reports): int
    {
        if (! Setting::get('catalog_open')) {
            $this->info('Katalog masih ditutup; tiada laporan dihantar.');

            return self::SUCCESS;
        }

        [$from, $to] = WeeklyReport::lastWeek();
        $sent = 0;

        User::query()
            ->where('role', Role::Parent)
            ->where('weekly_report', true)
            ->whereNotNull('email_verified_at')
            ->where(fn ($q) => $q->whereNull('weekly_report_sent_at')->orWhere('weekly_report_sent_at', '<', $to))
            ->where(fn ($q) => $q
                ->whereHas('children.readingSessions', fn ($q) => $q
                    ->whereBetween('read_on', [$from->toDateString(), $to->toDateString()])
                    ->where('minutes', '>', 0))
                ->orWhereHas('children.videoViews', fn ($q) => $q
                    ->whereBetween('watched_on', [$from->toDateString(), $to->toDateString()])
                    ->where('seconds', '>', 0)))
            ->chunkById(100, function ($parents) use ($reports, $from, $to, &$sent) {
                foreach ($parents as $parent) {
                    $children = $reports->forParent($parent, $from, $to);

                    if ($children === []) {
                        continue;
                    }

                    Mail::to($parent)->queue(new WeeklyReadingReport($parent, $children, $from, $to));
                    $parent->forceFill(['weekly_report_sent_at' => now()])->save();
                    $sent++;
                }
            });

        $this->info("{$sent} laporan mingguan dihantar ke baris gilir.");

        return self::SUCCESS;
    }
}
