<?php

namespace App\Support;

class Phone
{
    /**
     * Malaysian mobile numbers after normalisation, e.g. 0123456789 or 01123456789.
     */
    public const PATTERN = '/^01[0-9]{8,9}$/';

    /**
     * Any Malaysian number after normalisation, including landlines such as
     * 0361234567, for institutions that list an office line.
     */
    public const ANY_PATTERN = '/^0[1-9][0-9]{7,9}$/';

    /**
     * Strip spaces, dashes and a leading +60 / 60 so "+60 12-345 6789"
     * is stored as "0123456789".
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '60')) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }
}
