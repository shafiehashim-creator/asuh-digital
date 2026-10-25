<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['child_id', 'book_id', 'read_on', 'minutes', 'last_page', 'last_tick_at'])]
class ReadingSession extends Model
{
    protected function casts(): array
    {
        return [
            'read_on' => 'date',
            'minutes' => 'integer',
            'last_page' => 'integer',
            'last_tick_at' => 'datetime',
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
