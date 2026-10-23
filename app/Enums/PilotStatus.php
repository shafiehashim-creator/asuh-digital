<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PilotStatus: string implements HasLabel
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
}
