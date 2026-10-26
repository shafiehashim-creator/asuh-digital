<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Chip = 'chip';
    case BankTransfer = 'bank_transfer';
    // Given by an admin in the panel (complimentary, or paid outside the site, e.g. cash at a fair).
    case Manual = 'manual';

    /** What buyers may choose at checkout. */
    public static function forCheckout(): array
    {
        return [self::Chip, self::BankTransfer];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Chip => __('FPX / kad / e-wallet (CHIP)'),
            self::BankTransfer => __('Pindahan bank'),
            self::Manual => __('Manual (oleh ASUH)'),
        };
    }
}
