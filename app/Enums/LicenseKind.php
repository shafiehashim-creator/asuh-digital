<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LicenseKind: string implements HasLabel
{
    case Pilot = 'pilot';
    case Paid = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pilot => 'Pilot percuma',
            self::Paid => 'Berbayar',
        };
    }
}
