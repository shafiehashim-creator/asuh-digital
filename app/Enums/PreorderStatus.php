<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PreorderStatus: string implements HasLabel
{
    // Holds a slot until reserved_until while the buyer is paying.
    case Reserved = 'reserved';
    // Bank transfer receipt uploaded; slot stays held until an admin decides.
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Paid = 'paid';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Reserved => 'Slot ditempah',
            self::AwaitingConfirmation => 'Menunggu pengesahan',
            self::Paid => 'Dibayar',
            self::Expired => 'Tamat tempoh',
            self::Refunded => 'Dipulangkan',
        };
    }

    /**
     * Statuses that occupy one of the limited pre-order slots.
     *
     * @return list<self>
     */
    public static function holdingSlot(): array
    {
        return [self::Reserved, self::AwaitingConfirmation, self::Paid];
    }
}
