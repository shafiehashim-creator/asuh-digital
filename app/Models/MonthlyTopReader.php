<?php

namespace App\Models;

use App\Enums\AgeGroup;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One of the 5 best readers of an age group in a month, as announced by ASUH. */
#[Fillable(['month', 'age_group', 'rank', 'child_id', 'points', 'minutes', 'days', 'quizzes', 'reward_sent_at', 'reward_note'])]
class MonthlyTopReader extends Model
{
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'age_group' => AgeGroup::class,
            'reward_sent_at' => 'datetime',
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }
}
