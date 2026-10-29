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
            ? [1 => ['step', 'abcd', 'aabb', 'double'], 2 => ['down', 'alternate', 'wrong', 'wrong', 'triple'], 3 => ['growing', 'squares', 'woven', 'fibonacci', 'matrix', 'numgrid', 'numgrid']][$level]
            : [1 => ['ab', 'abc', 'aab'], 2 => ['abb', 'count2', 'count5', 'wrong', 'wrong'], 3 => ['matrix', 'matrix', 'numgrid', 'down', 'step']][$level];

        $questions = [];
        for ($i = 0; $i < $count * 3 && count($questions) < $count; $i++) {
            $kind = $kinds[array_rand($kinds)];
            $question = match ($kind) {
                'wrong' => self::wrong($older),
                'matrix' => self::matrix(),
                'numgrid' => self::numberGrid($older),
                default => self::question($kind),
            };
            // Never the same pattern twice in one game.
            $questions[$question['big']] = $question;
        }

        return array_values($questions);
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

    /** @return array<string, mixed> one number breaks the pattern: find it */
    private static function wrong(bool $older): array
    {
        $step = $older ? random_int(3, 9) : random_int(2, 5);
        $sequence = self::arithmetic(random_int(1, 20), $step, 5);
        $at = random_int(2, 5);
        $sequence[$at] += random_int(1, $step - 1) * (random_int(0, 1) ? 1 : -1);

        return [
            'prompt' => 'Nombor manakah yang TIDAK ikut corak?',
            'big' => implode('  ', $sequence),
            'answer' => (string) $sequence[$at],
            'options' => array_map('strval', $sequence),
            'keepOrder' => true,
        ];
    }

    /** @return array<string, mixed> each row and column has every picture once */
    private static function matrix(): array
    {
        [$a, $b, $c] = collect(self::EMOJI)->shuffle()->take(3)->all();
        $rows = random_int(0, 1) ? [[$a, $b, $c], [$b, $c, $a], [$c, $a, $b]] : [[$a, $b, $c], [$c, $a, $b], [$b, $c, $a]];
        $answer = $rows[2][2];
        $rows[2][2] = '❓';
        $others = collect(self::EMOJI)->diff([$a, $b, $c])->random(1)->all();

        return [
            'prompt' => 'Setiap baris dan lajur ada semua gambar sekali. Apa yang hilang?',
            'big' => implode("\n", array_map(fn (array $row) => implode('  ', $row), $rows)),
            'answer' => $answer,
            'options' => collect([$a, $b, $c])->merge($others)->shuffle()->values()->all(),
        ];
    }

    /** @return array<string, mixed> each row follows the same rule: find the missing number */
    private static function numberGrid(bool $older): array
    {
        $times = $older && random_int(0, 1);
        $rows = [];
        foreach (range(1, 3) as $i) {
            [$a, $b] = $times ? [random_int(2, 6), random_int(2, 6)] : [random_int(1, $older ? 20 : 9), random_int(1, $older ? 20 : 9)];
            $rows[] = [$a, $b, $times ? $a * $b : $a + $b];
        }
        $answer = $rows[2][2];
        $rows[2][2] = '❓';
        $others = collect([$answer + 1, $answer - 1, $answer + 2, $times ? $rows[2][0] + $rows[2][1] : $answer + 10])
            ->filter(fn ($n) => $n >= 0 && $n !== $answer)->unique()->take(3);

        return [
            'prompt' => 'Lihat setiap baris. Nombor apakah yang hilang?',
            'big' => implode("\n", array_map(fn (array $row) => implode('   ', $row), $rows)),
            'answer' => (string) $answer,
            'options' => $others->prepend($answer)->map(fn ($n) => (string) $n)->shuffle()->values()->all(),
        ];
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
