<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A teacher gives a Kuiz Interaktif to a class. */
#[Fillable(['quiz_game_id', 'classroom_id', 'assigned_by', 'due_at'])]
class QuizGameAssignment extends Model
{
    protected function casts(): array
    {
        return ['due_at' => 'datetime'];
    }

    public function quizGame(): BelongsTo
    {
        return $this->belongsTo(QuizGame::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function plays(): HasMany
    {
        return $this->hasMany(QuizGamePlay::class, 'assignment_id');
    }

    public function isOpen(): bool
    {
        return $this->due_at === null || $this->due_at->isFuture();
    }
}
