<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContributorRole: string implements HasLabel
{
    case Writer = 'penulis';
    case Illustrator = 'ilustrator';
    case Other = 'lain';

    public function getLabel(): string
    {
        return match ($this) {
            self::Writer => 'Penulis',
            self::Illustrator => 'Ilustrator',
            self::Other => 'Lain-lain',
        };
    }
}
