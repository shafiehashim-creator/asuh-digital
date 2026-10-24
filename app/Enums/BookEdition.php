<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BookEdition: string implements HasLabel
{
    case Original = 'original';
    case Enhanced = 'enhanced';

    public function getLabel(): string
    {
        return match ($this) {
            self::Original => 'Edisi asal',
            self::Enhanced => 'Edisi dipertingkat',
        };
    }
}
