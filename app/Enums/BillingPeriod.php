<?php

namespace App\Enums;

/**
 * How long a subscription runs per payment (user decision 2026-10-26).
 * Yearly is the default and the better deal; monthly is paid by hand each
 * month, with no automatic charges. Values are the BM words used in URLs.
 */
enum BillingPeriod: string
{
    case Yearly = 'tahunan';
    case Monthly = 'bulanan';

    public function label(): string
    {
        return match ($this) {
            self::Yearly => __('Tahunan'),
            self::Monthly => __('Bulanan'),
        };
    }

    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Yearly;
    }
}
