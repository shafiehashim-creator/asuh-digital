<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['child_id', 'video_id', 'watched_on', 'seconds', 'position', 'completed', 'last_tick_at'])]
class VideoView extends Model
{
    protected function casts(): array
    {
        return [
            'watched_on' => 'date',
            'seconds' => 'integer',
            'position' => 'integer',
            'completed' => 'boolean',
            'last_tick_at' => 'datetime',
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
