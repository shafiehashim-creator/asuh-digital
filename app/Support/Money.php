<?php

namespace App\Support;

class Money
{
    /**
     * Format an amount in sen as Malaysian ringgit, e.g. 7900 => "RM79.00".
     */
    public static function format(int $sen): string
    {
        return 'RM'.number_format($sen / 100, 2);
    }

    /**
     * Marketing format without empty sen, e.g. 7900 => "RM79", 7950 => "RM79.50".
     */
    public static function short(int $sen): string
    {
        return $sen % 100 === 0 ? 'RM'.number_format($sen / 100) : static::format($sen);
    }

    /**
     * Plain number for spreadsheets, e.g. 123456 => "1234.56".
     */
    public static function plain(int $sen): string
    {
        return number_format($sen / 100, 2, '.', '');
    }
}
