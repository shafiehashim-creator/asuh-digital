<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Safe to run on every deploy: existing plans (and any price the admin
     * has changed) are left untouched.
     */
    public function run(): void
    {
        $plans = [
            ['code' => Plan::PAS_TAHUNAN, 'name' => 'Langganan Tahunan', 'price_sen' => 14900, 'duration_months' => 12, 'is_active' => true],
            ['code' => Plan::PAS_TAHUNAN_EARLY, 'name' => 'Langganan Tahunan (Early Bird)', 'price_sen' => 7900, 'duration_months' => 12, 'is_active' => true],
            ['code' => Plan::PAS_PLUS, 'name' => 'Langganan Plus', 'price_sen' => 0, 'duration_months' => 12, 'is_active' => true],
            ['code' => Plan::PAS_BULANAN, 'name' => 'Langganan Bulanan', 'price_sen' => 1490, 'duration_months' => 1, 'is_active' => true],
            // 0 until the admin sets a price: not on sale.
            ['code' => Plan::PAS_PLUS_BULANAN, 'name' => 'Langganan Plus Bulanan', 'price_sen' => 0, 'duration_months' => 1, 'is_active' => true],
        ];

        foreach ($plans as $plan) {
            Plan::query()->firstOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
