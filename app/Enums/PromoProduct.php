<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** What an order is for, as far as promotion codes are concerned. */
enum PromoProduct: string implements HasLabel
{
    case All = 'all';
    case Pass = 'pass';
    case Plus = 'plus';
    case Book = 'book';
    // Monthly subscriptions (e-books or Plus); school codes stay yearly only.
    case Monthly = 'monthly';
    // Upgrades to Plus already pay only the difference; no codes apply.
    case Upgrade = 'upgrade';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'Semua produk',
            self::Pass => 'Langganan Tahunan',
            self::Plus => 'Langganan Plus',
            self::Book => 'E-book single',
            self::Monthly => 'Langganan bulanan',
            self::Upgrade => 'Naik taraf',
        };
    }

    /** @return array<string, string> choices for a coupon */
    public static function couponOptions(): array
    {
        return collect([self::All, self::Pass, self::Plus, self::Monthly, self::Book])
            ->mapWithKeys(fn (self $p) => [$p->value => $p->getLabel()])->all();
    }
}
