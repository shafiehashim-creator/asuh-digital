<?php

namespace App\Support;

/**
 * The site's languages (user decision 2026-10-26): Bahasa Melayu first and
 * by default, English as the second. BM sentences are the translation keys,
 * so lang/en.json maps each BM sentence to English.
 */
class Locale
{
    public const DEFAULT = 'ms';

    public const SUPPORTED = ['ms' => 'BM', 'en' => 'EN'];

    public const SESSION_KEY = 'locale';

    public const COOKIE = 'asuh_lang';

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::SUPPORTED);
    }

    public static function isEnglish(): bool
    {
        return app()->getLocale() === 'en';
    }
}
