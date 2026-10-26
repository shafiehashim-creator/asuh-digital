<?php

namespace App\Services;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use App\Models\Setting;

/**
 * The plans on sale for each subscription and billing period: Langganan
 * Tahunan or Bulanan (e-books), and Langganan Plus yearly or monthly
 * (e-books and videos). A plan is on sale when active and priced above 0;
 * Plus also needs the videos to be open.
 */
class SubscriptionPlans
{
    public function pass(BillingPeriod $period): ?Plan
    {
        return $this->onSale($period === BillingPeriod::Yearly ? Plan::PAS_TAHUNAN : Plan::PAS_BULANAN);
    }

    public function plus(BillingPeriod $period): ?Plan
    {
        if (! Setting::get('videos_open') || ! Setting::get('plus_sales_open')) {
            return null;
        }

        return $this->onSale($period === BillingPeriod::Yearly ? Plan::PAS_PLUS : Plan::PAS_PLUS_BULANAN);
    }

    /** @return array<string, Plan> the periods on sale for 'pass' or 'plus', keyed by period value */
    public function periods(string $product): array
    {
        $plans = [];
        foreach (BillingPeriod::cases() as $period) {
            if ($plan = $product === 'plus' ? $this->plus($period) : $this->pass($period)) {
                $plans[$period->value] = $plan;
            }
        }

        return $plans;
    }

    /** How much cheaper a year is than twelve months, in whole percent (0 when not cheaper). */
    public static function yearlySavingPercent(Plan $yearly, Plan $monthly): int
    {
        $twelveMonths = $monthly->price_sen * 12;

        return $twelveMonths > $yearly->price_sen ? (int) round(100 - $yearly->price_sen / $twelveMonths * 100) : 0;
    }

    private function onSale(string $code): ?Plan
    {
        $plan = Plan::findByCode($code);

        return $plan?->is_active && $plan->price_sen > 0 ? $plan : null;
    }
}
