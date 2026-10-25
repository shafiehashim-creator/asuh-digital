<?php

namespace App\Support;

use App\Enums\VideoProvider;

/**
 * Turns what an editor pastes (a full YouTube link or just the id) into the
 * provider's video id.
 */
class VideoId
{
    public static function normalise(VideoProvider $provider, string $input): string
    {
        $input = trim($input);

        if ($provider === VideoProvider::YouTube
            && preg_match('~(?:youtu\.be/|[?&]v=|/embed/|/shorts/|/live/)([A-Za-z0-9_-]{11})~', $input, $match)) {
            return $match[1];
        }

        return $input;
    }

    public static function isValid(VideoProvider $provider, string $id): bool
    {
        return match ($provider) {
            VideoProvider::YouTube => (bool) preg_match('/^[A-Za-z0-9_-]{11}$/', $id),
            // Bunny Stream video ids are GUIDs.
            VideoProvider::Bunny => (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id),
        };
    }
}
