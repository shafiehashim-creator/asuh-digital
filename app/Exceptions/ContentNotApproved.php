<?php

namespace App\Exceptions;

/**
 * CLAUDE.md: nothing is published without a passed syariah review (videos
 * as well as books).
 */
class ContentNotApproved extends BookNotApproved
{
    public static function video(): self
    {
        return new self('Video tidak boleh diterbitkan tanpa semakan syariah yang lulus.');
    }
}
