<?php

namespace App\Models;

use App\Enums\AgeGroup;
use App\Enums\Game;
use Illuminate\Database\Eloquent\Model;

/**
 * One piece of game content, written in the admin (/admin/permainan): a
 * memory pair, a good-or-not scenario, a set to order, a hidden word, a
 * prophet clue or a quick manners question. Only active items are played.
 */
class GameItem extends Model
{
    protected $fillable = ['game', 'age_group', 'variant', 'prompt', 'answer', 'is_good', 'explanation', 'options', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'game' => Game::class,
            'age_group' => AgeGroup::class,
            'is_good' => 'boolean',
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
