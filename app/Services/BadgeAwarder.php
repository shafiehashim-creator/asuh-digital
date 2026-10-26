<?php

namespace App\Services;

use App\Enums\Badge;
use App\Models\Child;
use App\Models\ChildBadge;
use App\Models\ReadingSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Gives badges for quizzes passed and for reading on many days (CLAUDE.md:
 * never for pages turned). Safe to call often: earned badges stay earned and
 * are never given twice.
 */
class BadgeAwarder
{
    /** Minutes in a day for it to count as a reading day. */
    public const MIN_MINUTES_PER_DAY = 5;

    /**
     * @return list<Badge> badges earned just now
     */
    public function check(Child $child): array
    {
        $have = $child->badges()->pluck('badge')->map(fn ($badge) => $badge->value)->all();
        $new = [];

        foreach ($this->earned($child) as $badge) {
            if (in_array($badge->value, $have, true)) {
                continue;
            }
            ChildBadge::query()->insertOrIgnore(['child_id' => $child->id, 'badge' => $badge->value, 'awarded_at' => now()]);
            $new[] = $badge;
        }

        // A parent may have promised a reward for one of these.
        app(RewardPromises::class)->badgesEarned($child, $new);

        return $new;
    }

    /** @return list<Badge> */
    private function earned(Child $child): array
    {
        $passed = $child->quizAttempts()->where('passed', true)->distinct()->count('quiz_id');
        $perfect = $child->quizAttempts()->whereColumn('score', 'total')->distinct()->count('quiz_id');
        $days = $this->readingDays($child);
        $streak = $this->longestStreak($days);
        $bestMonth = $days->countBy(fn (string $day) => substr($day, 0, 7))->max() ?? 0;

        return array_values(array_filter([
            $passed >= 1 ? Badge::FirstQuiz : null,
            $passed >= 5 ? Badge::QuizExpert : null,
            $perfect >= 10 ? Badge::KnowledgeStar : null,
            $streak >= 3 ? Badge::KeenReader : null,
            $streak >= 7 ? Badge::ReadingWeek : null,
            $bestMonth >= 20 ? Badge::SteadyReader : null,
        ]));
    }

    /**
     * Days with enough reading, as Y-m-d strings in order.
     *
     * @return Collection<int, string>
     */
    public function readingDays(Child $child): Collection
    {
        return ReadingSession::query()
            ->where('child_id', $child->id)
            ->selectRaw('read_on, SUM(minutes) as total')
            ->groupBy('read_on')
            ->havingRaw('SUM(minutes) >= ?', [self::MIN_MINUTES_PER_DAY])
            ->orderBy('read_on')
            ->pluck('read_on')
            ->map(fn ($day) => Carbon::parse($day)->toDateString())
            ->unique()
            ->values();
    }

    /** @param Collection<int, string> $days */
    private function longestStreak(Collection $days): int
    {
        $best = 0;
        $run = 0;
        $previous = null;

        foreach ($days as $day) {
            $date = Carbon::parse($day);
            $run = $previous && $previous->copy()->addDay()->isSameDay($date) ? $run + 1 : 1;
            $best = max($best, $run);
            $previous = $date;
        }

        return $best;
    }
}
