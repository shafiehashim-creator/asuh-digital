<?php

namespace App\Support;

/**
 * Sums for the Matematik Pantas game, made fresh for every game: adding and
 * taking away for 6–9, times and sharing for 10–13, harder at each level,
 * with short stories about Anis and Anas.
 */
class MathQuestions
{
    /** @return list<array{prompt: string, big: ?string, answer: string, options: list<string>}> */
    public static function make(bool $older, int $level, int $count): array
    {
        $questions = [];
        for ($i = 0; $i < $count; $i++) {
            [$prompt, $big, $answer] = $older ? self::older($level) : self::younger($level);
            $questions[] = ['prompt' => $prompt, 'big' => $big, 'answer' => (string) $answer, 'options' => self::options($answer, $older)];
        }

        return $questions;
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
