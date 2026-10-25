<?php

namespace App\Services;

use App\Enums\Badge;
use App\Models\Child;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * What each child read in a week, for the parent's weekly e-mail.
 */
class WeeklyReport
{
    public function __construct(private FamilyProgress $progress) {}

    /** The Monday-to-Sunday week before $now. */
    public static function lastWeek(?Carbon $now = null): array
    {
        $start = ($now ?? now())->copy()->subWeek()->startOfWeek(Carbon::MONDAY);

        return [$start, $start->copy()->endOfWeek(Carbon::SUNDAY)];
    }

    /**
     * One entry per child who read in the week; children who did not read are left out.
     *
     * @return list<array{child: Child, minutes: int, video_minutes: int, days: int, books: list<string>, quizzes: list<string>, badges: list<Badge>}>
     */
    public function forParent(User $parent, Carbon $from, Carbon $to): array
    {
        $report = [];

        foreach ($parent->children()->get() as $child) {
            $week = $this->progress->between($child, $from, $to);

            if ($week['minutes'] === 0 && $week['video_minutes'] === 0) {
                continue;
            }

            $report[] = $week;
        }

        return $report;
    }
}
