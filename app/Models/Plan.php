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

    // Monthly versions (user decision 2026-10-26): paid by hand each month,
    // priced so that the yearly plans are the better deal.
    public const PAS_BULANAN = 'pas_bulanan';

    public const PAS_PLUS_BULANAN = 'pas_plus_bulanan';

    /** Plans that include the animated videos (Phase 2). */
    public const PLUS_CODES = [self::PAS_PLUS, self::PAS_PLUS_BULANAN];

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

    public function isPlus(): bool
    {
        return in_array($this->code, self::PLUS_CODES, true);
    }

    public function isMonthly(): bool
    {
        return $this->duration_months === 1;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
