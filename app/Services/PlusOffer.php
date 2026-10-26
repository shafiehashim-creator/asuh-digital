<?php

namespace App\Services;

use App\Models\Pass;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Langganan Plus (Phase 2): e-books and the animated videos. On sale once
 * the admin opens the videos and sets a price. Families who already have a
 * Langganan Tahunan upgrade it for the days left, paying the difference.
 */
class PlusOffer
{
    public function __construct(private Campaigns $campaigns) {}

    public function plan(): ?Plan
    {
        $plan = Plan::findByCode(Plan::PAS_PLUS);

        return $plan?->is_active && $plan->price_sen > 0 ? $plan : null;
    }

    /** The yearly price now: the running campaign's promotional price, else the plan price. */
    public function priceSen(): ?int
    {
        $plan = $this->plan();
        $promo = $this->campaigns->plusPromo()?->promo_price_sen;

        return $plan ? ($promo && $promo < $plan->price_sen ? $promo : $plan->price_sen) : null;
    }

    /** The normal price, shown struck through while a campaign lowers it. */
    public function compareAtSen(): ?int
    {
        $plan = $this->plan();

        return $plan && $this->priceSen() < $plan->price_sen ? $plan->price_sen : null;
    }

    /** Shown to the public: the videos are open and Plus has a price. */
    public function available(): bool
    {
        return (bool) Setting::get('videos_open') && $this->plan() !== null;
    }

    /**
     * Sold, not only shown. While the admin holds sales back, the sales page
     * takes "Beritahu saya" sign-ups and points families to the yearly plan.
     */
    public function onSale(): bool
    {
        return $this->available() && (bool) Setting::get('plus_sales_open');
    }

    /** The opening date announced on the sales page while Plus is not sold yet. */
    public function opensOn(): ?CarbonImmutable
    {
        $date = Setting::get('plus_opens_on');

        return filled($date) ? CarbonImmutable::parse($date)->startOfDay() : null;
    }

    /** The subscription a family would upgrade: the current one, or one waiting for the launch date. */
    public function upgradablePass(User $user): ?Pass
    {
        $pass = $user->activePass() ?? $user->passes()->whereNull('starts_at')->latest()->first();

        // Monthly subscribers buy Plus instead: upgrades are for yearly subscriptions.
        return $pass && ! $pass->isPlus() && ! $pass->plan?->isMonthly() ? $pass : null;
    }

    /**
     * The price difference for the days left, rounded up to whole ringgit,
     * or null when there is nothing to upgrade.
     */
    public function upgradePriceSen(Pass $pass): ?int
    {
        $plus = $this->plan();
        $difference = $plus ? $this->priceSen() - ($pass->plan?->price_sen ?? 0) : 0;

        if ($difference <= 0) {
            return null;
        }

        // A subscription waiting for the launch date still has all its days.
        $share = 1.0;
        if ($pass->starts_at && $pass->ends_at) {
            $total = max(1, $pass->starts_at->diffInDays($pass->ends_at));
            $share = min(1.0, max(0.0, now()->diffInDays($pass->ends_at) / $total));
        }

        $sen = (int) ceil($difference * $share / 100) * 100;

        return $sen > 0 ? $sen : null;
    }
}
