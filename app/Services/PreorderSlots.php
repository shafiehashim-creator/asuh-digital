<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Preorder;
use App\Models\Setting;

/**
 * Read-only view of the limited pre-order slots. The checkout (step 4)
 * reserves slots under a lock; this class only counts them.
 */
class PreorderSlots
{
    public function limit(): int
    {
        return (int) Setting::get('preorder_slot_limit');
    }

    public function taken(): int
    {
        return Preorder::query()->holdingSlot()->count();
    }

    public function remaining(): int
    {
        return max(0, $this->limit() - $this->taken());
    }

    public function isSoldOut(): bool
    {
        return $this->remaining() === 0;
    }

    public function plan(): ?Plan
    {
        return Plan::findByCode(Setting::get('preorder_plan_code'));
    }
}
