<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AgeGroup: string implements HasLabel
{
    case SixToNine = '6-9';
    case TenToThirteen = '10-13';
    case Both = 'both';

    public function getLabel(): string
    {
        return match ($this) {
            self::SixToNine => __('6–9 tahun'),
            self::TenToThirteen => __('10–13 tahun'),
            self::Both => __('Kedua-duanya'),
        };
    }
}
