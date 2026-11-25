<?php

namespace App\Services\Audio;

use Illuminate\Support\Str;

/**
 * Puts the spoken word times onto the words printed on the page. The two
 * lists are close but not equal (staff may tidy the spoken text, the PDF may
 * split a word), so they are matched by the longest common run of words.
 */
class WordMatcher
{
    /**
     * @param  list<array{t: string, s: float, e: float}>  $spoken
     * @param  list<array{t: string, x: float, y: float, w: float, h: float}>  $boxes
     * @return list<array{t: string, s: float, e: float, b: ?list<float>}>
     */
    public static function attachBoxes(array $spoken, array $boxes): array
    {
        $a = array_map(fn ($w) => self::normal($w['t']), $spoken);
        $b = array_map(fn ($w) => self::normal($w['t']), $boxes);
        $n = count($a);
        $m = count($b);

        // Longest common subsequence, filled from the end.
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] !== '' && $a[$i] === $b[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $out = array_map(fn ($w) => $w + ['b' => null], $spoken);
        for ($i = 0, $j = 0; $i < $n && $j < $m;) {
            if ($a[$i] !== '' && $a[$i] === $b[$j]) {
                $box = $boxes[$j];
                $out[$i]['b'] = [round($box['x'], 4), round($box['y'], 4), round($box['w'], 4), round($box['h'], 4)];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return $out;
    }

    /** Share of spoken words that found a place on the page. */
    public static function coverage(array $matched): float
    {
        return $matched === [] ? 0.0 : count(array_filter($matched, fn ($w) => $w['b'] !== null)) / count($matched);
    }

    private static function normal(string $word): string
    {
        return preg_replace('/[^\p{L}\p{N}]/u', '', Str::lower(Str::ascii($word)));
    }
}
