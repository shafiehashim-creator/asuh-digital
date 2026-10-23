<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'price_sen', 'duration_months', 'is_active'])]
class Plan extends Model
{
    public const PAS_TAHUNAN = 'pas_tahunan';

    public const PAS_TAHUNAN_EARLY = 'pas_tahunan_early';

    public const PAS_PLUS = 'pas_plus';

    protected function casts(): array
    {
        return [
            'price_sen' => 'integer',
            'duration_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', $code)->first();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
