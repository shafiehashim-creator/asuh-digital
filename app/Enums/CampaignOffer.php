<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a campaign switches on between its start and end. "None" only shows
 * the countdown; the other two set the price in that window.
 */
enum CampaignOffer: string implements HasLabel
{
    case None = 'none';
    case EarlyBird = 'early_bird';
    case PlusPromo = 'plus';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Tiada (countdown sahaja)',
            self::EarlyBird => 'Pre-order early bird Langganan Tahunan',
            self::PlusPromo => 'Harga promosi Langganan Plus',
        };
    }
}
