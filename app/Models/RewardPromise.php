<?php

namespace App\Models;

use App\Enums\Badge;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A parent's promise: earn this badge and get this reward. */
#[Fillable(['child_id', 'badge', 'reward', 'achieved_at', 'fulfilled_at'])]
class RewardPromise extends Model
{
    /** Promises waiting at the same time, per child. */
    public const MAX_OPEN = 3;

    protected function casts(): array
    {
        return ['badge' => Badge::class, 'achieved_at' => 'datetime', 'fulfilled_at' => 'datetime'];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function isAchieved(): bool
    {
        return $this->achieved_at !== null;
    }

    public function isFulfilled(): bool
    {
        return $this->fulfilled_at !== null;
    }
}
