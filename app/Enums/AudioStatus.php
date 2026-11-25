<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Where a book page's voice stands. */
enum AudioStatus: string implements HasColor, HasLabel
{
    case None = 'none';
    case Queued = 'queued';
    case Ready = 'ready';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Tiada audio',
            self::Queued => 'Sedang dijana…',
            self::Ready => 'Sedia',
            self::Failed => 'Gagal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Queued => 'warning',
            self::Ready => 'success',
            self::Failed => 'danger',
        };
    }
}
