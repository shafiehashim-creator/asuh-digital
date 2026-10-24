<?php

namespace App\Exceptions;

use RuntimeException;

class PreorderUnavailable extends RuntimeException
{
    public static function soldOut(): self
    {
        return new self('Maaf, semua slot early bird telah ditempah.');
    }

    public static function closed(): self
    {
        return new self('Pre-order tidak dibuka buat masa ini.');
    }
}
