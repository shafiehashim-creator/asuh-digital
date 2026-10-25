<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Facades\Route;

/**
 * The yearly pass as it is on sale right now, shared by the landing page,
 * book pages and the pass sales page: the early bird while its slots last,
 * then the regular pass, or nothing when neither is on sale.
 */
class PassOffer
{
    public function __construct(private PreorderSlots $slots) {}

    public function earlyBird(): bool
    {
        return (bool) $this->slots->plan()?->is_active && ! $this->slots->isSoldOut();
    }

    public function regularPlan(): ?Plan
    {
        $plan = Plan::findByCode(Plan::PAS_TAHUNAN);

        return $plan?->is_active ? $plan : null;
    }

    public function plan(): ?Plan
    {
        return $this->earlyBird() ? $this->slots->plan() : $this->regularPlan();
    }

    public function available(): bool
    {
        return $this->plan() !== null;
    }

    public function priceSen(): ?int
    {
        return $this->plan()?->price_sen;
    }

    /** The regular price shown struck through next to the early bird price. */
    public function compareAtSen(): ?int
    {
        $regular = Plan::findByCode(Plan::PAS_TAHUNAN);

        return $this->earlyBird() && $regular && $regular->price_sen > $this->priceSen()
            ? $regular->price_sen
            : null;
    }

    public function checkoutUrl(): string
    {
        return $this->earlyBird() && Route::has('preorder.create') ? route('preorder.create') : route('purchase.pass');
    }

    public function ctaLabel(): string
    {
        return $this->earlyBird() ? 'Tempah slot saya' : 'Beli Langganan Tahunan';
    }

    public function slotLimit(): int
    {
        return $this->slots->limit();
    }

    public function slotsRemaining(): int
    {
        return $this->slots->remaining();
    }
}
