<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Chip = 'chip';
    case BankTransfer = 'bank_transfer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Chip => 'FPX / kad / e-wallet (CHIP)',
            self::BankTransfer => 'Pindahan bank',
        };
    }
}
