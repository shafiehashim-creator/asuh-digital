<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Hash;

/**
 * The check before leaving Mod Anak (CLAUDE.md): the parent's 4-digit PIN when
 * one is set, otherwise a random multiplication written out in words, which
 * young readers find hard to work out at a glance.
 */
class ParentGate
{
    private const SESSION_KEY = 'parent_gate_answer';

    private const ONES = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'lapan', 'sembilan'];

    public function __construct(private Session $session) {}

    /** A fresh question; its answer waits in the session. */
    public function newQuestion(): string
    {
        $a = random_int(12, 39);
        $b = random_int(3, 9);
        $this->session->put(self::SESSION_KEY, $a * $b);

        return 'Berapakah '.self::words($a).' darab '.self::words($b).'?';
    }

    public function passes(User $parent, string $answer): bool
    {
        if ($parent->hasParentPin()) {
            return Hash::check($answer, $parent->parent_pin);
        }

        $expected = $this->session->pull(self::SESSION_KEY);

        return $expected !== null && trim($answer) === (string) $expected;
    }

    /** Malay number words for 1–99. */
    public static function words(int $n): string
    {
        return match (true) {
            $n < 10 => self::ONES[$n],
            $n === 10 => 'sepuluh',
            $n === 11 => 'sebelas',
            $n < 20 => self::ONES[$n - 10].' belas',
            default => trim(self::ONES[intdiv($n, 10)].' puluh '.self::ONES[$n % 10]),
        };
    }
}
