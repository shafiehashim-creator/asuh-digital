<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PilotStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Contacted = 'contacted';
    case PilotActive = 'pilot_active';
    case Paying = 'paying';
    case Declined = 'declined';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Contacted => 'Dihubungi',
            self::PilotActive => 'Pilot aktif',
            self::Paying => 'Membayar',
            self::Declined => 'Tidak meneruskan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Contacted => 'warning',
            self::PilotActive => 'primary',
            self::Paying => 'success',
            self::Declined => 'gray',
        };
    }
}
