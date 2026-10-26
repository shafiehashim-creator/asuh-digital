<?php

namespace App\Models;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PromoProduct;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['code', 'description', 'type', 'value', 'applies_to', 'starts_at', 'ends_at', 'max_uses', 'once_per_customer', 'is_active'])]
class Coupon extends Model
{
    use HasFactory;

    /** A coupon never brings a price below this (CHIP cannot take RM0). */
    public const MIN_PRICE_SEN = 100;

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'applies_to' => PromoProduct::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_uses' => 'integer',
            'once_per_customer' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (Coupon $coupon) => $coupon->code = Str::upper(trim($coupon->code)));
    }

    public static function findByCode(?string $code): ?self
    {
        $code = Str::upper(trim((string) $code));

        return $code === '' ? null : static::query()->where('code', $code)->first();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function usesCount(): int
    {
        return $this->orders()->where('status', OrderStatus::Paid)->count();
    }

    public function appliesTo(PromoProduct $product): bool
    {
        return $product !== PromoProduct::Upgrade
            && ($this->applies_to === PromoProduct::All || $this->applies_to === $product);
    }

    public function discountSen(int $priceSen): int
    {
        $discount = $this->type === CouponType::Percent
            ? (int) round($priceSen * min($this->value, 100) / 100)
            : $this->value;

        return max(0, min($discount, $priceSen - self::MIN_PRICE_SEN));
    }

    public function describeValue(): string
    {
        return $this->type === CouponType::Percent ? $this->value.'%' : Money::format($this->value);
    }
}
