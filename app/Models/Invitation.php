<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An e-mail invitation to join an institution as its administrator or a teacher. */
#[Fillable(['institution_id', 'email', 'name', 'role', 'token_hash', 'invited_by', 'expires_at', 'accepted_at'])]
#[Hidden(['token_hash'])]
class Invitation extends Model
{
    public const VALID_DAYS = 7;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public static function findByToken(string $token): ?self
    {
        return static::query()->where('token_hash', hash('sha256', $token))->first();
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
