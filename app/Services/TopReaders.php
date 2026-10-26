<?php

namespace App\Services;

use App\Enums\AgeGroup;
use App\Enums\Badge;
use App\Mail\TopReaderWinner;
use App\Models\Child;
use App\Models\ChildBadge;
use App\Models\MonthlyTopReader;
use App\Models\QuizAttempt;
use App\Models\ReadingSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * ASUH's 5 best readers of each age group every month (user decision
 * 2026-10-26). Family children whose parent subscribed that month take part;
 * pupils on a school licence do not. Points reward steady reading and
 * understanding, not leaving a book open:
 *  - reading minutes, at most 60 counted per day;
 *  - 10 points for each reading day (5 minutes or more);
 *  - 20 points for each book quiz passed for the first time that month.
 * Only the admin sees the ranking; winners' parents are told by e-mail.
 */
class TopReaders
{
    public const WINNERS = 5;

    public const MAX_MINUTES_PER_DAY = 60;

    public const POINTS_PER_DAY = 10;

    public const POINTS_PER_QUIZ = 20;

    /** The two age groups ranked separately. */
    public const GROUPS = [AgeGroup::SixToNine, AgeGroup::TenToThirteen];

    /**
     * The ranking of one age group for a month, best first.
     *
     * @return Collection<int, array{child: Child, points: int, minutes: int, days: int, quizzes: int}>
     */
    public function ranking(Carbon $month, AgeGroup $group, int $limit = 20): Collection
    {
        [$from, $to] = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];

        // Family children whose parent had a subscription during the month.
        $children = Child::query()->with('user')
            ->whereNotNull('user_id')->where('age_group', $group)
            ->whereHas('user.passes', fn ($q) => $q->where('starts_at', '<=', $to)->where('ends_at', '>=', $from))
            ->get()->keyBy('id');

        if ($children->isEmpty()) {
            return collect();
        }

        $days = ReadingSession::query()
            ->whereIn('child_id', $children->keys())
            ->whereBetween('read_on', [$from->toDateString(), $to->toDateString()])
            ->groupBy('child_id', 'read_on')
            ->select('child_id', DB::raw('SUM(minutes) as total'))
            ->get()
            ->groupBy('child_id');

        // Quizzes passed this month that were not passed before it.
        $quizzes = QuizAttempt::query()
            ->whereIn('child_id', $children->keys())->where('passed', true)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotExists(fn ($q) => $q->from('quiz_attempts as earlier')
                ->whereColumn('earlier.child_id', 'quiz_attempts.child_id')->whereColumn('earlier.quiz_id', 'quiz_attempts.quiz_id')
                ->where('earlier.passed', true)->where('earlier.created_at', '<', $from))
            ->select('child_id', DB::raw('COUNT(DISTINCT quiz_id) as passed'))
            ->groupBy('child_id')
            ->pluck('passed', 'child_id');

        return $children
            ->map(function (Child $child) use ($days, $quizzes) {
                $totals = ($days->get($child->id) ?? collect())->pluck('total')->map(fn ($minutes) => (int) $minutes);
                $minutes = (int) $totals->sum(fn (int $total) => min($total, self::MAX_MINUTES_PER_DAY));
                $readingDays = $totals->filter(fn (int $total) => $total >= BadgeAwarder::MIN_MINUTES_PER_DAY)->count();
                $passed = (int) ($quizzes[$child->id] ?? 0);

                return [
                    'child' => $child,
                    'points' => $minutes + $readingDays * self::POINTS_PER_DAY + $passed * self::POINTS_PER_QUIZ,
                    'minutes' => $minutes,
                    'days' => $readingDays,
                    'quizzes' => $passed,
                ];
            })
            ->filter(fn (array $row) => $row['points'] > 0)
            ->sortBy([['points', 'desc'], ['quizzes', 'desc'], ['days', 'desc']])
            ->take($limit)
            ->values();
    }

    /** A month can be announced once it is over, and only once. */
    public function canAnnounce(Carbon $month): bool
    {
        return $month->copy()->endOfMonth()->isPast() && ! $this->announced($month);
    }

    public function announced(Carbon $month): bool
    {
        return MonthlyTopReader::query()->whereDate('month', $month->copy()->startOfMonth())->exists();
    }

    /**
     * Records the 5 best of each age group, gives them the Pembaca Terbaik
     * badge and e-mails their parents.
     *
     * @return int winners announced
     */
    public function announce(Carbon $month): int
    {
        if (! $this->canAnnounce($month)) {
            return 0;
        }

        $count = 0;
        foreach (self::GROUPS as $group) {
            foreach ($this->ranking($month, $group, self::WINNERS) as $i => $row) {
                $winner = MonthlyTopReader::query()->create([
                    'month' => $month->copy()->startOfMonth(), 'age_group' => $group, 'rank' => $i + 1,
                    'child_id' => $row['child']->id, 'points' => $row['points'],
                    'minutes' => $row['minutes'], 'days' => $row['days'], 'quizzes' => $row['quizzes'],
                ]);

                $inserted = ChildBadge::query()->insertOrIgnore(['child_id' => $row['child']->id, 'badge' => Badge::TopReader->value, 'awarded_at' => now()]);
                if ($inserted) {
                    app(RewardPromises::class)->badgesEarned($row['child'], [Badge::TopReader]);
                }

                Mail::to($row['child']->user)->queue(new TopReaderWinner($winner));
                $count++;
            }
        }

        return $count;
    }
}
