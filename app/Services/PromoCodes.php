<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PromoProduct;
use App\Models\Affiliate;
use App\Models\Coupon;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The one "Kod promosi" box at checkout (user decision 2026-10-26: one code
 * per order). It takes a school parent code, a coupon, or an affiliate code.
 * An affiliate's ?ref= link is remembered in a cookie and still credits the
 * affiliate when the buyer types a discount code (see Order::booted).
 */
class PromoCodes
{
    public const COOKIE = 'asuh_ref';

    /**
     * @return array{discount_sen: int, referral_institution_id: ?int, coupon_id: ?int, affiliate_id: ?int}
     *
     * @throws ValidationException when a typed code cannot be used
     */
    public function apply(?string $code, PromoProduct $product, ?User $buyer, int $priceSen): array
    {
        $result = ['discount_sen' => 0, 'referral_institution_id' => null, 'coupon_id' => null, 'affiliate_id' => null];
        $code = trim((string) $code);

        if ($code !== '') {
            if ($school = Institution::findByParentCode($code)) {
                $this->ensure(in_array($product, [PromoProduct::Pass, PromoProduct::Plus], true), 'Kod sekolah hanya untuk Langganan Tahunan dan Langganan Plus baharu.');
                $result['referral_institution_id'] = $school->id;
                $result['discount_sen'] = (int) round($priceSen * $school->parent_discount_percent / 100);
            } elseif ($coupon = Coupon::findByCode($code)) {
                $this->checkCoupon($coupon, $product, $buyer);
                $result['coupon_id'] = $coupon->id;
                $result['discount_sen'] = $coupon->discountSen($priceSen);
            } elseif ($affiliate = Affiliate::findActiveByCode($code)) {
                $this->ensure($affiliate->user_id !== $buyer?->id, 'Anda tidak boleh menggunakan kod affiliate anda sendiri.');
                $result['affiliate_id'] = $affiliate->id;
            } else {
                $this->ensure(false, 'Kod promosi tidak sah. Semak ejaan kod.');
            }
        }

        return $result;
    }

    /**
     * The affiliate whose ?ref= link brought this visitor (remembered in a
     * cookie), never the buyer themselves. Filled in on every new order.
     */
    public function referredAffiliateId(?int $buyerId): ?int
    {
        $affiliate = Affiliate::findActiveByCode(request()->cookie(self::COOKIE));

        return $affiliate && $affiliate->user_id !== $buyerId ? $affiliate->id : null;
    }

    private function checkCoupon(Coupon $coupon, PromoProduct $product, ?User $buyer): void
    {
        $this->ensure($coupon->is_active, 'Kod promosi ini tidak lagi aktif.');
        $this->ensure(! $coupon->starts_at || $coupon->starts_at->isPast(), 'Kod promosi ini belum bermula.');
        $this->ensure(! $coupon->ends_at || $coupon->ends_at->isFuture(), 'Kod promosi ini sudah tamat tempoh.');
        $this->ensure($coupon->appliesTo($product), 'Kod promosi ini tidak boleh digunakan untuk produk ini.');
        $this->ensure(! $coupon->max_uses || $coupon->usesCount() < $coupon->max_uses, 'Kod promosi ini sudah habis digunakan.');
        $this->ensure(
            ! $coupon->once_per_customer || ! $buyer || ! $buyer->orders()->where('coupon_id', $coupon->id)->where('status', OrderStatus::Paid)->exists(),
            'Anda sudah menggunakan kod promosi ini.',
        );
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['promo_code' => $message]);
        }
    }
}
