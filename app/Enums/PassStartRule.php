<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PassStartRule: string implements HasLabel
{
    case OnPayment = 'on_payment';
    case OnLaunch = 'on_launch';

    public function getLabel(): string
    {
        return match ($this) {
            self::OnPayment => 'Bermula selepas bayaran disahkan',
            self::OnLaunch => 'Bermula pada tarikh launch platform',
        };
    }
}
