<?php

namespace App\Services\Chip;

use App\Enums\ChipMethod;
use App\Models\Setting;

/**
 * The CHIP processing fee the buyer pays on top of the price, set by the
 * admin per method as a fixed amount (RM) plus a percentage. Cards: none.
 */
class ProcessingFee
{
    public function for(ChipMethod $method, int $priceSen): int
    {
        if (! $method->mayCarryFee() || $priceSen <= 0) {
            return 0;
        }

        $fixed = (float) Setting::get("chip_fee_{$method->value}_fixed");
        $percent = (float) Setting::get("chip_fee_{$method->value}_percent");

        // Round up to the next sen so the fee always covers CHIP's charge.
        return (int) round($fixed * 100) + (int) ceil($priceSen * $percent / 100);
    }

    /** @return array<string, int> method value => fee in sen */
    public function all(int $priceSen): array
    {
        return collect(ChipMethod::cases())->mapWithKeys(fn (ChipMethod $m) => [$m->value => $this->for($m, $priceSen)])->all();
    }
}
