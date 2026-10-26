<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CommissionStatus: string implements HasColor, HasLabel
{
    // Waiting out the refund window (payable_at), then due for payment.
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Belum dibayar',
            self::Paid => 'Dibayar',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'gray',
        };
    }
}
