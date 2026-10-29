<?php

namespace App\Support;

/**
 * Sums for the Matematik Pantas game, made fresh for every game: adding and
 * taking away for 6–9, times and sharing for 10–13, with short stories about
 * Anis and Anas. Each level adds a different kind of thinking: comparing and
 * missing numbers (Sederhana), then picking two numbers that make a target
 * and balancing both sides of an equation (Sukar).
 */
class MathQuestions
{
    /** @return list<array{prompt: string, big: ?string, answer: string, options: list<string>}> */
    public static function make(bool $older, int $level, int $count): array
    {
        $questions = [];
        for ($i = 0; $i < $count * 2 && count($questions) < $count; $i++) {
            $roll = random_int(1, 10);
            $question = match (true) {
                $level === 2 && $roll <= 3 => self::compare($older),
                $level === 2 && $roll <= 6 => self::missing($older),
                $level === 3 && $roll <= 4 => self::pickTwo($older),
                $level === 3 && $roll <= 7 => self::balance($older),
                default => self::basic($older, $level),
            };
            // Never the same sum twice in one game.
            $questions[$question['prompt'].$question['big']] = $question;
        }

        return array_values($questions);
    }

    /** @return array<string, mixed> */
    private static function basic(bool $older, int $level): array
    {
        [$prompt, $big, $answer] = $older ? self::older($level) : self::younger($level);

        return ['prompt' => $prompt, 'big' => $big, 'answer' => (string) $answer, 'options' => self::options($answer, $older)];
    }

    /** @return array<string, mixed> which sign goes between two sides */
    private static function compare(bool $older): array
    {
        [$left, $a] = $older ? self::product() : self::sum(20);
        [$right, $b] = random_int(1, 10) <= 3 ? self::sameValue($a, $older) : ($older ? self::product() : self::sum(20));
        $sign = $a <=> $b;

        return [
            'prompt' => 'Pilih tanda yang betul: lebih kecil, sama atau lebih besar?',
            'big' => "{$left}  ❓  {$right}",
            'answer' => [-1 => '<', 0 => '=', 1 => '>'][$sign],
            'options' => ['<', '=', '>'],
            'keepOrder' => true,
        ];
    }

    /** @return array<string, mixed> one number of a sum is hidden */
    private static function missing(bool $older): array
    {
        if ($older) {
            [$a, $b] = [random_int(2, 12), random_int(2, 12)];

            return random_int(0, 1)
                ? self::numeric('Nombor apakah yang hilang?', "❓ × {$b} = ".($a * $b), $a, true)
                : self::numeric('Nombor apakah yang hilang?', ($a * $b)." ÷ ❓ = {$a}", $b, true);
        }

        [$a, $b] = [random_int(1, 12), random_int(1, 8)];

        return random_int(0, 1)
            ? self::numeric('Nombor apakah yang hilang?', "{$a} + ❓ = ".($a + $b), $b, false)
            : self::numeric('Nombor apakah yang hilang?', ($a + $b)." − ❓ = {$a}", $b, false);
    }

    /** @return array<string, mixed> pick the two numbers that make the target */
    private static function pickTwo(bool $older): array
    {
        do {
            [$a, $b] = $older ? [random_int(2, 12), random_int(2, 12)] : [random_int(1, 12), random_int(1, 12)];
        } while ($a === $b);

        $op = $older ? '×' : '+';
        $target = $older ? $a * $b : $a + $b;
        $numbers = collect([$a, $b]);
        while ($numbers->count() < 5) {
            $n = random_int(1, $older ? 12 : 15);
            // A distractor never makes the target with another number.
            $clash = $numbers->contains(fn ($m) => $older ? $m * $n === $target : $m + $n === $target);
            if (! $numbers->contains($n) && ! $clash && $n * 2 !== $target && $n * $n !== $target) {
                $numbers->push($n);
            }
        }

        return [
            'prompt' => $older ? "Pilih DUA nombor yang hasil darabnya {$target}" : "Pilih DUA nombor yang jumlahnya {$target}",
            'big' => "🎯 {$target}",
            'answer' => "{$a} {$op} {$b} = {$target}",
            'options' => $numbers->shuffle()->map(fn ($n) => (string) $n)->values()->all(),
            'pick' => 2,
            'op' => $older ? 'times' : 'plus',
            'target' => $target,
        ];
    }

    /** @return array<string, mixed> both sides must be equal */
    private static function balance(bool $older): array
    {
        if ($older) {
            [$b, $c] = [random_int(2, 9), random_int(2, 9)];
            $a = random_int(1, $b * $c - 1);

            return self::numeric('Seimbangkan! Nombor apakah yang hilang?', "❓ + {$a} = {$b} × {$c}", $b * $c - $a, true);
        }

        [$b, $c] = [random_int(2, 9), random_int(2, 9)];
        $a = random_int(1, $b + $c - 1);

        return self::numeric('Seimbangkan! Nombor apakah yang hilang?', "❓ + {$a} = {$b} + {$c}", $b + $c - $a, false);
    }

    /** @return array<string, mixed> */
    private static function numeric(string $prompt, string $big, int $answer, bool $older): array
    {
        return ['prompt' => $prompt, 'big' => $big, 'answer' => (string) $answer, 'options' => self::options($answer, $older)];
    }

    /** @return array{0: string, 1: int} */
    private static function sum(int $max): array
    {
        $a = random_int(1, $max - 1);
        $b = random_int(1, $max - $a);

        return ["{$a} + {$b}", $a + $b];
    }

    /** @return array{0: string, 1: int} */
    private static function product(): array
    {
        [$a, $b] = [random_int(2, 9), random_int(2, 9)];

        return ["{$a} × {$b}", $a * $b];
    }

    /** @return array{0: string, 1: int} another way to write the same number */
    private static function sameValue(int $value, bool $older): array
    {
        if ($older) {
            foreach ([2, 3, 4, 5, 6] as $d) {
                if ($value % $d === 0 && $value / $d <= 12) {
                    return [($value / $d)." × {$d}", $value];
                }
            }

            return [($value - 1).' + 1', $value];
        }

        $a = random_int(0, $value);

        return ["{$a} + ".($value - $a), $value];
    }

    /** @return array{0: string, 1: ?string, 2: int} */
    private static function younger(int $level): array
    {
        $max = [1 => 10, 2 => 20, 3 => 60][$level];
        $story = $level > 1 && random_int(1, 10) <= 3;

        if (random_int(0, 1) === 0) {
            $a = random_int(1, $max - 1);
            $b = random_int(1, $max - $a);

            if ($level === 3 && random_int(0, 2) === 0) {
                return ['Nombor apakah yang hilang?', "❓ + {$b} = ".($a + $b), $a];
            }

            return $story ? [self::story('add', $a, $b), null, $a + $b] : ['Berapakah jawapannya?', "{$a} + {$b} = ❓", $a + $b];
        }

        $a = random_int(2, $max);
        $b = random_int(1, $a - 1);

        return $story ? [self::story('take', $a, $b), null, $a - $b] : ['Berapakah jawapannya?', "{$a} − {$b} = ❓", $a - $b];
    }

    /** @return array{0: string, 1: ?string, 2: int} */
    private static function older(int $level): array
    {
        $kind = random_int(1, 10);

        if ($level === 1) {
            if ($kind <= 4) {
                $a = random_int(10, 89);
                $b = random_int(5, 99 - $a);

                return ['Berapakah jawapannya?', "{$a} + {$b} = ❓", $a + $b];
            }
            if ($kind <= 6) {
                $a = random_int(20, 99);
                $b = random_int(5, $a - 1);

                return ['Berapakah jawapannya?', "{$a} − {$b} = ❓", $a - $b];
            }
            [$a, $b] = [random_int(2, 5), random_int(1, 10)];

            return ['Berapakah jawapannya?', "{$a} × {$b} = ❓", $a * $b];
        }

        [$a, $b] = [random_int(2, 10), random_int(2, 10)];

        if ($level === 2) {
            return match (true) {
                $kind <= 4 => ['Berapakah jawapannya?', "{$a} × {$b} = ❓", $a * $b],
                $kind <= 7 => ['Berapakah jawapannya?', ($a * $b)." ÷ {$b} = ❓", $a],
                $kind <= 9 => [self::story('times', $a, $b), null, $a * $b],
                default => [self::story('share', $a * $b, $b), null, $a],
            };
        }

        $c = random_int(1, min(20, $a * $b - 1));

        return match (true) {
            $kind <= 3 => ['Berapakah jawapannya?', "{$a} × {$b} + {$c} = ❓", $a * $b + $c],
            $kind <= 5 => ['Berapakah jawapannya?', "{$a} × {$b} − {$c} = ❓", $a * $b - $c],
            $kind <= 7 => ['Nombor apakah yang hilang?', "❓ × {$b} = ".($a * $b), $a],
            $kind <= 8 => ['Berapakah jawapannya?', ($a * $b * 10)." ÷ {$b} = ❓", $a * 10],
            $kind === 9 => [self::story('times', $a, $b), null, $a * $b],
            default => [self::story('share', $a * $b, $b), null, $a],
        };
    }

    private static function story(string $kind, int $a, int $b): string
    {
        $stories = [
            'add' => [
                "Anis ada {$a} biji kurma. Ibu beri {$b} biji lagi. Berapa biji kurma Anis sekarang?",
                "Anas membaca {$a} muka surat pagi tadi dan {$b} muka surat pada waktu petang. Berapa muka surat semuanya?",
            ],
            'take' => [
                "Anas ada {$a} biji gula-gula. Dia sedekah {$b} biji kepada kawan. Berapa biji yang tinggal?",
                "Ada {$a} ekor burung di atas pokok. {$b} ekor terbang pergi. Berapa ekor yang tinggal?",
            ],
            'times' => [
                "Ada {$a} saf solat. Setiap saf ada {$b} orang. Berapa orang semuanya?",
                "Anis membaca {$b} muka surat setiap hari. Berapa muka surat yang dibacanya dalam {$a} hari?",
            ],
            'share' => [
                "{$a} biji kurma dibahagi sama rata kepada {$b} orang. Berapa biji yang didapati seorang?",
                "Anas menyusun {$a} buah buku sama banyak di atas {$b} rak. Berapa buah buku di setiap rak?",
            ],
        ];

        return $stories[$kind][array_rand($stories[$kind])];
    }

    /** @return list<string> the answer and nearby wrong answers */
    private static function options(int $answer, bool $older): array
    {
        $near = $older ? [1, 2, 10, -1, -2, -10, 5] : [1, 2, 3, -1, -2, -3];
        $options = collect($near)->map(fn (int $d) => $answer + $d)
            ->filter(fn (int $n) => $n >= 0 && $n !== $answer)
            ->shuffle()->take(5)
            ->prepend($answer)
            ->map(fn (int $n) => (string) $n);

        return $options->unique()->values()->all();
    }
}
