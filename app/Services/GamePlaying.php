<?php

namespace App\Services;

use App\Enums\Game;
use App\Models\Child;
use App\Models\GamePlay;
use App\Models\Setting;

/**
 * Games in Mod Anak (user decision 2026-10-27): for children of families
 * with an active subscription (not pupils), behind the admin's switch, and
 * within the daily limit the parent sets on the child's profile.
 */
class GamePlaying
{
    /** Never count more than this for one heartbeat (sent every 30 seconds). */
    public const MAX_TICK_SECONDS = 35;

    /** Daily limits a parent can pick, in minutes; none means no limit. */
    public const LIMIT_CHOICES = [10, 20, 30, 45, 60];

    public function isOpen(): bool
    {
        return (bool) Setting::get('games_open');
    }

    public function hasAccess(Child $child): bool
    {
        if ($child->isPupil() || ! $child->user) {
            return false;
        }

        if ($child->user->isStaff()) {
            return true;
        }

        return $this->isOpen() && $child->user->passes()->active()->exists();
    }

    /** Seconds still allowed today, or null when the parent set no limit. */
    public function secondsLeftToday(Child $child): ?int
    {
        if (! $child->daily_game_minutes) {
            return null;
        }

        $played = (int) GamePlay::query()->where('child_id', $child->id)->whereDate('played_on', today())->sum('seconds');

        return max(0, $child->daily_game_minutes * 60 - $played);
    }

    public function record(Child $child, Game $game, int $seconds, ?int $score): GamePlay
    {
        $play = GamePlay::query()->firstOrCreate(['child_id' => $child->id, 'game' => $game, 'played_on' => today()]);

        // Never more than the time really passed since the last heartbeat.
        $elapsed = $play->last_tick_at ? (int) $play->last_tick_at->diffInSeconds(now()) : self::MAX_TICK_SECONDS;
        $seconds = max(0, min($seconds, self::MAX_TICK_SECONDS, $elapsed));

        $left = $this->secondsLeftToday($child);
        if ($left !== null) {
            $seconds = min($seconds, $left);
        }

        $play->seconds += $seconds;
        if ($score !== null) {
            $play->rounds++;
            $play->best_score = max($play->best_score, min($score, 100));
        }
        $play->last_tick_at = now();
        $play->save();

        return $play;
    }

    /** The child's best score per game, on any day. @return array<string, int> */
    public function bestScores(Child $child): array
    {
        return GamePlay::query()->where('child_id', $child->id)
            ->selectRaw('game, max(best_score) as best')->groupBy('game')->pluck('best', 'game')->map(fn ($v) => (int) $v)->all();
    }
}
