<?php

namespace App\Models;

use App\Enums\Game;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Time a child played one game on one day, and the best score that day. */
class GamePlay extends Model
{
    protected $fillable = ['child_id', 'game', 'played_on'];

    protected function casts(): array
    {
        return [
            'game' => Game::class,
            'played_on' => 'date',
            'last_tick_at' => 'datetime',
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }
}
