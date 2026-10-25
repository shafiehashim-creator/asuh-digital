<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['visited_at', 'visitor', 'user_id', 'route', 'path', 'source', 'medium', 'campaign', 'device'])]
class PageVisit extends Model
{
    use MassPrunable;

    public $timestamps = false;

    /** Kept for a little over a year, enough to compare with last year. */
    public const KEEP_DAYS = 400;

    protected function casts(): array
    {
        return ['visited_at' => 'datetime'];
    }

    public function prunable(): Builder
    {
        return static::query()->where('visited_at', '<', now()->subDays(self::KEEP_DAYS));
    }
}
