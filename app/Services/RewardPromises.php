<?php

namespace App\Services;

use App\Enums\Badge;
use App\Mail\RewardPromiseAchieved;
use App\Models\Child;
use App\Models\RewardPromise;
use Illuminate\Support\Facades\Mail;

/**
 * Parents' reward promises (user decision 2026-10-26): "earn this badge and
 * you get this reward". A promise is achieved the moment the child earns the
 * badge; the parent is told by e-mail and marks it fulfilled.
 */
class RewardPromises
{
    /** @param list<Badge> $badges badges the child has just earned */
    public function badgesEarned(Child $child, array $badges): void
    {
        if ($badges === []) {
            return;
        }

        $promises = $child->rewardPromises()->whereNull('achieved_at')
            ->whereIn('badge', array_map(fn (Badge $badge) => $badge->value, $badges))->get();

        foreach ($promises as $promise) {
            $promise->forceFill(['achieved_at' => now()])->save();

            if ($child->user) {
                Mail::to($child->user)->queue(new RewardPromiseAchieved($promise));
            }
        }
    }

    /**
     * Badges a promise can still be made for: not earned yet and not
     * promised already.
     *
     * @return list<Badge>
     */
    public function promisable(Child $child): array
    {
        $taken = $child->badges->pluck('badge')->concat($child->rewardPromises->pluck('badge'))
            ->map(fn (Badge $badge) => $badge->value)->all();

        return array_values(array_filter(Badge::cases(), fn (Badge $badge) => ! in_array($badge->value, $taken, true)));
    }

    public function canAddMore(Child $child): bool
    {
        return $child->rewardPromises->whereNull('achieved_at')->count() < RewardPromise::MAX_OPEN;
    }
}
