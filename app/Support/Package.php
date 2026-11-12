<?php

namespace App\Support;

use App\Enums\Game;
use App\Services\GamePlaying;
use App\Services\PlusOffer;

/**
 * What a family subscription opens, in one place (user decision
 * 2026-11-12), so the home page, the sales page, the pre-order form and the
 * checkout always describe the same package. Every family subscription (the
 * early bird, yearly and monthly) opens the same things; only the price and
 * the length differ.
 */
class Package
{
    public static function includesVideos(): bool
    {
        $plus = app(PlusOffer::class);

        return $plus->merged() && $plus->videosPublic();
    }

    public static function includesGames(): bool
    {
        return app(GamePlaying::class)->isOpen();
    }

    /** @return list<string> the checklist shown wherever the package is sold */
    public static function contents(): array
    {
        return array_values(array_filter([
            __('Semua e-book Islamik ASUH Digital'),
            self::includesVideos() ? __('Semua video animasi ASUH') : null,
            self::includesGames() ? __(':count permainan minda dengan tiga tahap', ['count' => count(Game::cases())]) : null,
            __('Sehingga 4 profil anak, di telefon, tablet dan komputer'),
            __('Tiada caj automatik'),
        ]));
    }

    /** The package in a few words, for sentences: "e-book, video animasi dan permainan minda". */
    public static function summary(): string
    {
        return match (true) {
            self::includesVideos() && self::includesGames() => __('e-book, video animasi dan permainan minda'),
            self::includesVideos() => __('e-book dan video animasi'),
            self::includesGames() => __('e-book dan permainan minda'),
            default => __('e-book'),
        };
    }
}
