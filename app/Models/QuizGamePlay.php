<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One go at a Kuiz Interaktif, by a child or by an adult trying it. */
#[Fillable(['quiz_game_id', 'child_id', 'user_id', 'assignment_id', 'score', 'correct', 'total', 'answers', 'finished_at'])]
class QuizGamePlay extends Model
{
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'finished_at' => 'datetime',
            'score' => 'integer',
            'correct' => 'integer',
            'total' => 'integer',
        ];
    }

    public function quizGame(): BelongsTo
    {
        return $this->belongsTo(QuizGame::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(QuizGameAssignment::class);
    }
}
