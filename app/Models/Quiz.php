<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['book_id', 'is_active'])]
class Quiz extends Model
{
    /** CLAUDE.md decision (2026-10-25): 3–5 questions, pass at 80%. */
    public const MIN_QUESTIONS = 3;

    public const MAX_QUESTIONS = 5;

    public const PASS_PERCENT = 80;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /** Ready for children: switched on and with enough questions. */
    public function isPlayable(): bool
    {
        return $this->is_active && $this->questions()->count() >= self::MIN_QUESTIONS;
    }

    public static function passes(int $score, int $total): bool
    {
        return $total > 0 && $score * 100 >= $total * self::PASS_PERCENT;
    }
}
