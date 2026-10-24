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
}
