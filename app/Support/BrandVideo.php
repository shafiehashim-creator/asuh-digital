<?php

namespace App\Support;

use App\Enums\VideoProvider;
use App\Models\Setting;

/**
 * The ASUH music video (a YouTube link set by the admin), shown on the
 * pages the admin picks: home, about, the parent's account and Mod Anak.
 */
class BrandVideo
{
    public const PLACES = [
        'home' => 'Laman utama',
        'about' => 'Tentang kami',
        'account' => 'Akaun ibu bapa (Ringkasan)',
        'child_mode' => 'Mod Anak (rak anak)',
    ];

    public static function id(): ?string
    {
        $id = VideoId::normalise(VideoProvider::YouTube, (string) Setting::get('brand_video_url'));

        return VideoId::isValid(VideoProvider::YouTube, $id) ? $id : null;
    }

    public static function title(): string
    {
        return Setting::get('brand_video_title') ?: 'Lagu ASUH';
    }

    public static function shownOn(string $place): bool
    {
        return self::id() !== null && in_array($place, (array) Setting::get('brand_video_places'), true);
    }
}
