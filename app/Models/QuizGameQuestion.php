<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quiz_game_id', 'sort', 'type', 'prompt', 'image_path', 'options', 'answer', 'seconds', 'explanation'])]
class QuizGameQuestion extends Model
{
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'options' => 'array',
            'answer' => 'array',
            'seconds' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function quizGame(): BelongsTo
    {
        return $this->belongsTo(QuizGame::class);
    }
}
