<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quiz_id', 'question', 'options', 'correct_index', 'sort_order'])]
class QuizQuestion extends Model
{
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_index' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
