<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * CLAUDE.md: a book may not be published without a passed syariah review.
 */
class BookNotApproved extends RuntimeException
{
    public static function make(): self
    {
        return new self('Buku tidak boleh diterbitkan tanpa semakan syariah yang lulus.');
    }
}
