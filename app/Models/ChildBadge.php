<?php

namespace App\Models;

use App\Enums\Badge;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['child_id', 'badge', 'awarded_at'])]
class ChildBadge extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'badge' => Badge::class,
            'awarded_at' => 'datetime',
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }
}
