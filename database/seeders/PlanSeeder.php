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
            ['code' => Plan::PAS_TAHUNAN, 'name' => 'Pas Tahunan', 'price_sen' => 9900, 'duration_months' => 12, 'is_active' => true],
            ['code' => Plan::PAS_TAHUNAN_EARLY, 'name' => 'Pas Tahunan (Early Bird)', 'price_sen' => 7900, 'duration_months' => 12, 'is_active' => true],
            ['code' => Plan::PAS_PLUS, 'name' => 'Pas Tahunan Plus', 'price_sen' => 0, 'duration_months' => 12, 'is_active' => false],
        ];

        foreach ($plans as $plan) {
            Plan::query()->firstOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
