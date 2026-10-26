<?php

namespace App\Exceptions;

use RuntimeException;

class PreorderUnavailable extends RuntimeException
{
    public static function soldOut(): self
    {
        return new self(__('Maaf, semua slot early bird telah ditempah.'));
    }

    public static function closed(): self
    {
        return new self(__('Pre-order tidak dibuka buat masa ini.'));
    }
}
