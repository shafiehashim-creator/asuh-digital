<?php

namespace App\Models;

use App\Enums\AgeGroup;
use App\Enums\ChildAvatar;
use Database\Factories\ChildFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nickname', 'age_group', 'avatar'])]
class Child extends Model
{
    /** @use HasFactory<ChildFactory> */
    use HasFactory;

    /** CLAUDE.md: at most 4 children per account. */
    public const MAX_PER_ACCOUNT = 4;

    protected function casts(): array
    {
        return [
            'age_group' => AgeGroup::class,
            'avatar' => ChildAvatar::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
