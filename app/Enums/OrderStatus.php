<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu bayaran',
            self::Paid => 'Dibayar',
            self::Failed => 'Gagal',
            self::Refunded => 'Dipulangkan',
        };
    }
}
