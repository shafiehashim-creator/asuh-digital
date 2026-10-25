<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a video's file lives. Switching provider means uploading the file
 * there and changing the video's provider and id; nothing else.
 */
enum VideoProvider: string implements HasLabel
{
    case YouTube = 'youtube';
    case Bunny = 'bunny';

    public function getLabel(): string
    {
        return match ($this) {
            self::YouTube => 'YouTube (unlisted)',
            self::Bunny => 'Bunny Stream',
        };
    }
}
