<?php

namespace App\Support;

/**
 * "What comes next?" questions for the Teka Corak game, made fresh for
 * every game: repeating pictures for the youngest, then counting on, and
 * at the top levels times, growing gaps and two patterns woven together.
 */
class PatternQuestions
{
    private const EMOJI = ['🌙', '⭐', '🕌', '📖', '🌴', '🐪', '🍇', '💧', '☀️', '🤲'];

    /** @return list<array{prompt: string, big: string, answer: string, options: list<string>}> */
    public static function make(bool $older, int $level, int $count): array
    {
        $kinds = $older
            ? [1 => ['step', 'abcd', 'aabb'], 2 => ['double', 'down', 'alternate'], 3 => ['growing', 'triple', 'squares', 'woven', 'fibonacci']][$level]
            : [1 => ['ab', 'abc'], 2 => ['aab', 'abb', 'count1', 'count2'], 3 => ['abcd', 'count5', 'down', 'step']][$level];

        $questions = [];
        for ($i = 0; $i < $count; $i++) {
            $questions[] = self::question($kinds[array_rand($kinds)]);
        }

        return $questions;
    }

    /** @return array{prompt: string, big: string, answer: string, options: list<string>} */
    private static function question(string $kind): array
    {
        $pictures = ['ab' => 2, 'abc' => 3, 'aab' => 2, 'abb' => 2, 'abcd' => 4, 'aabb' => 2];

        if (isset($pictures[$kind])) {
            $set = collect(self::EMOJI)->shuffle()->take($pictures[$kind])->values()->all();
            $unit = match ($kind) {
                'aab' => [$set[0], $set[0], $set[1]],
                'abb' => [$set[0], $set[1], $set[1]],
                'aabb' => [$set[0], $set[0], $set[1], $set[1]],
                default => $set,
            };
            $length = random_int(min(count($unit) + 2, 5), 5);
            $sequence = array_map(fn (int $i) => $unit[$i % count($unit)], range(0, $length));
            $answer = array_pop($sequence);
            $others = collect(self::EMOJI)->diff([$answer])->shuffle()->take(3)->merge(array_diff($set, [$answer]))->unique();

            return self::shape($sequence, $answer, $others->all());
        }

        $sequence = match ($kind) {
            'count1' => self::arithmetic(random_int(1, 10), 1, 5),
            'count2' => self::arithmetic(random_int(0, 10) * 2, 2, 5),
            'count5' => self::arithmetic(random_int(0, 4) * 5, 5, 5),
            'step' => self::arithmetic(random_int(1, 20), random_int(2, 10), 5),
            'down' => self::arithmetic(random_int(30, 60), -random_int(1, 7), 5),
            'double' => self::geometric(random_int(1, 5), 2, 5),
            'triple' => self::geometric(random_int(1, 2), 3, 4),
            'alternate' => self::alternate(random_int(1, 10), random_int(1, 3), random_int(4, 6), 6),
            'growing' => self::growing(random_int(1, 10), random_int(1, 3), 6),
            'squares' => self::squares(random_int(1, 4), 5),
            'woven' => self::woven(random_int(1, 5), random_int(2, 5) * 10, 6),
            'fibonacci' => self::fibonacci(random_int(1, 3), 6),
        };
        $answer = array_pop($sequence);
        $gap = abs($answer - end($sequence)) ?: 1;
        $others = collect([$answer + 1, $answer - 1, $answer + $gap, $answer - $gap, $answer + 2])
            ->filter(fn (int $n) => $n >= 0 && $n !== $answer)->unique()->shuffle()->take(4);

        return self::shape($sequence, $answer, $others->all());
    }

    /** @return array{prompt: string, big: string, answer: string, options: list<string>} */
    private static function shape(array $sequence, int|string $answer, array $others): array
    {
        return [
            'prompt' => 'Apa yang datang seterusnya?',
            'big' => implode('  ', [...$sequence, '❓']),
            'answer' => (string) $answer,
            'options' => array_values(array_unique(array_map('strval', [$answer, ...$others]))),
        ];
    }

    /** @return list<int> */
    private static function arithmetic(int $start, int $step, int $shown): array
    {
        return array_map(fn (int $i) => $start + $step * $i, range(0, $shown));
    }

    /** @return list<int> */
    private static function geometric(int $start, int $ratio, int $shown): array
    {
        return array_map(fn (int $i) => $start * $ratio ** $i, range(0, $shown));
    }

    /** @return list<int> +a, +b, +a, +b … */
    private static function alternate(int $start, int $a, int $b, int $shown): array
    {
        $out = [$start];
        for ($i = 1; $i <= $shown; $i++) {
            $out[] = end($out) + ($i % 2 ? $a : $b);
        }

        return $out;
    }

    /** @return list<int> gaps growing by one: 1, 2, 4, 7, 11 … */
    private static function growing(int $start, int $firstGap, int $shown): array
    {
        $out = [$start];
        for ($i = 0; $i < $shown; $i++) {
            $out[] = end($out) + $firstGap + $i;
        }

        return $out;
    }

    /** @return list<int> 1, 4, 9, 16 … from the given number */
    private static function squares(int $from, int $shown): array
    {
        return array_map(fn (int $n) => $n * $n, range($from, $from + $shown));
    }

    /** @return list<int> two patterns in turn: 1, 10, 2, 20, 3, 30 … */
    private static function woven(int $start, int $tens, int $shown): array
    {
        return array_map(fn (int $i) => $i % 2 ? (intdiv($i, 2) + 1) * $tens : $start + intdiv($i, 2), range(0, $shown));
    }

    /** @return list<int> each number is the sum of the two before it */
    private static function fibonacci(int $start, int $shown): array
    {
        $out = [$start, $start];
        while (count($out) <= $shown) {
            $out[] = $out[count($out) - 1] + $out[count($out) - 2];
        }

        return $out;
    }
}
