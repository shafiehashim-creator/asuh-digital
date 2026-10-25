<?php

namespace App\Support;

use App\Enums\VideoProvider;
use App\Models\Video;

/**
 * What the player needs for a video, built only after the access check.
 * YouTube runs through its IFrame API with ASUH's own controls (no-cookie
 * domain, no related videos); Bunny Stream through its embed, with a
 * short-lived token when a token key is configured.
 */
class VideoEmbed
{
    /** How long a Bunny embed token lasts. */
    private const BUNNY_TOKEN_MINUTES = 240;

    /** @return array{provider: string, id: string, url: ?string} */
    public static function for(Video $video): array
    {
        return [
            'provider' => $video->provider->value,
            'id' => $video->provider_video_id,
            'url' => $video->provider === VideoProvider::Bunny ? self::bunnyUrl($video->provider_video_id) : null,
        ];
    }

    private static function bunnyUrl(string $id): string
    {
        $library = config('services.bunny_stream.library_id');
        $url = "https://iframe.mediadelivery.net/embed/{$library}/{$id}?autoplay=false&preload=true&responsive=true";
        $key = config('services.bunny_stream.token_key');

        if ($key) {
            $expires = now()->addMinutes(self::BUNNY_TOKEN_MINUTES)->timestamp;
            $url .= '&token='.hash('sha256', $key.$id.$expires).'&expires='.$expires;
        }

        return $url;
    }
}
