<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Enums\PromoProduct;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Coupon> */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'PROMO'.Str::upper(Str::random(4)),
            'type' => CouponType::Percent,
            'value' => 20,
            'applies_to' => PromoProduct::All,
            'once_per_customer' => true,
            'is_active' => true,
        ];
    }
}
