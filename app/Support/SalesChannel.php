<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Groups where a visit or order came from into the channels of the sales
 * report, from its UTM tags or referring site.
 */
class SalesChannel
{
    public const DIRECT = 'Terus';

    public const ADS = 'Iklan berbayar';

    public const SOCIAL = 'Media sosial';

    public const SEARCH = 'Carian';

    public const EMAIL = 'E-mel';

    public const REFERRAL = 'Laman lain';

    public const SCHOOL = 'Kod sekolah';

    public const AFFILIATE = 'Affiliate';

    public const COUPON = 'Kupon promosi';

    public const MANUAL = 'Manual (booth / admin)';

    private const SOCIAL_HOSTS = ['facebook', 'fb.', 'instagram', 'tiktok', 'whatsapp', 'wa.me', 't.co', 'x.com', 'twitter', 'youtube', 'youtu.be', 'telegram', 'threads', 'linkedin', 'pinterest'];

    private const SEARCH_HOSTS = ['google.', 'bing.', 'yahoo.', 'duckduckgo', 'baidu', 'yandex', 'ecosia'];

    private const PAID_MEDIUMS = ['cpc', 'ppc', 'paid', 'ads', 'ad', 'paid_social', 'paidsocial', 'display', 'cpm'];

    public static function from(?string $source, ?string $medium): string
    {
        $source = Str::lower((string) $source);
        $medium = Str::lower((string) $medium);

        return match (true) {
            $source === '' || $source === 'terus' => self::DIRECT,
            in_array($medium, self::PAID_MEDIUMS, true) => self::ADS,
            $medium === 'email' || $medium === 'e-mel' => self::EMAIL,
            Str::contains($source, self::SOCIAL_HOSTS) || $medium === 'social' => self::SOCIAL,
            Str::contains($source, self::SEARCH_HOSTS) || $medium === 'organic' => self::SEARCH,
            default => self::REFERRAL,
        };
    }
}
