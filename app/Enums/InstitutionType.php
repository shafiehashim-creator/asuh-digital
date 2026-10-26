<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InstitutionType: string implements HasLabel
{
    case Kindergarten = 'tadika';
    case Sk = 'sk';
    case Sra = 'sra';
    case Kafa = 'kafa';
    case Tahfiz = 'tahfiz';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Kindergarten => 'Tadika',
            self::Sk => 'Sekolah Kebangsaan (SK)',
            self::Sra => 'Sekolah Rendah Agama',
            self::Kafa => 'KAFA',
            self::Tahfiz => 'Tahfiz',
            self::Other => 'Lain-lain',
        };
    }
}
