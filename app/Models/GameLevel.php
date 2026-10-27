<?php

namespace App\Models;

use App\Enums\Game;
use Illuminate\Database\Eloquent\Model;

/** The highest level (1 Mudah, 2 Sederhana, 3 Sukar) a child has opened in one game. */
class GameLevel extends Model
{
    protected $fillable = ['child_id', 'game', 'unlocked'];

    protected function casts(): array
    {
        return ['game' => Game::class, 'unlocked' => 'integer'];
    }
}
