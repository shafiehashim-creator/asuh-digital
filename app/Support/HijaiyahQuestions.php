<?php

namespace App\Support;

/**
 * Kenal Huruf Hijaiyah, a different task at each level: name the letter
 * (Mudah), find the letter for a name among look-alikes (Sederhana), and name
 * a letter in its joined form at the start, middle or end of a word (Sukar).
 */
class HijaiyahQuestions
{
    private const LETTERS = [
        ['ا', 'Alif'], ['ب', 'Ba'], ['ت', 'Ta'], ['ث', 'Tsa'], ['ج', 'Jim'], ['ح', 'Ha'], ['خ', 'Kho'],
        ['د', 'Dal'], ['ذ', 'Zal'], ['ر', 'Ra'], ['ز', 'Zai'], ['س', 'Sin'], ['ش', 'Syin'], ['ص', 'Sod'],
        ['ض', 'Dhod'], ['ط', 'Tho'], ['ظ', 'Zho'], ['ع', 'Ain'], ['غ', 'Ghain'], ['ف', 'Fa'], ['ق', 'Qof'],
        ['ك', 'Kaf'], ['ل', 'Lam'], ['م', 'Mim'], ['ن', 'Nun'], ['و', 'Wau'], ['ه', 'Ha'], ['ي', 'Ya'],
    ];

    /** Letters that differ only by their dots or a small stroke: the hard distractors. */
    private const LOOK_ALIKES = [['ب', 'ت', 'ث', 'ن', 'ي'], ['ج', 'ح', 'خ'], ['د', 'ذ'], ['ر', 'ز'], ['س', 'ش'], ['ص', 'ض'], ['ط', 'ظ'], ['ع', 'غ'], ['ف', 'ق']];

    /** These join only to the letter before them, so they have no start or middle form. */
    private const RIGHT_JOINING = ['ا', 'د', 'ذ', 'ر', 'ز', 'و'];

    private const TATWEEL = 'ـ';

    /** @return list<array<string, mixed>> */
    public static function make(bool $older, int $level): array
    {
        $letters = $older ? self::LETTERS : array_slice(self::LETTERS, 0, $level === 1 ? 10 : 14);

        return match ($level) {
            1 => array_map(fn (array $l) => self::question('Apakah nama huruf ini?', $l[0], $l[1], self::names($letters), true), $letters),
            // Both letters called "Ha" are left out, as their name alone cannot tell them apart.
            2 => array_values(array_map(fn (array $l) => [
                'prompt' => "Tekan huruf {$l[1]}",
                'big' => null,
                'answer' => $l[0],
                'options' => self::lookAlikes($l[0], array_column($letters, 0)),
            ], array_filter($letters, fn (array $l) => $l[1] !== 'Ha'))),
            default => array_map(function (array $l) use ($letters) {
                [$form, $where] = self::joinedForm($l[0]);

                return self::question("Huruf apakah ini? (di {$where} perkataan)", $form, $l[1], self::nameLookAlikes($l, $letters), true);
            }, $letters),
        };
    }

    /** @return array<string, mixed> */
    private static function question(string $prompt, string $big, string $answer, array $options, bool $rtl): array
    {
        return ['prompt' => $prompt, 'big' => $big, 'rtl' => $rtl, 'answer' => $answer, 'options' => array_values(array_unique($options))];
    }

    /** @return list<string> */
    private static function names(array $letters): array
    {
        return array_values(array_unique(array_column($letters, 1)));
    }

    /** @return list<string> the letter first, then its look-alikes, then any others */
    private static function lookAlikes(string $letter, array $pool): array
    {
        $group = collect(self::LOOK_ALIKES)->first(fn (array $g) => in_array($letter, $g, true)) ?? [];
        $similar = collect($group)->reject(fn ($l) => $l === $letter)->shuffle();
        $others = collect($pool)->reject(fn ($l) => $l === $letter || in_array($l, $group, true))->shuffle();

        return $similar->merge($others)->take(3)->prepend($letter)->values()->all();
    }

    /** @return list<string> the name first, then names of look-alike letters */
    private static function nameLookAlikes(array $letter, array $letters): array
    {
        $byGlyph = array_column(self::LETTERS, 1, 0);
        $similar = collect(self::lookAlikes($letter[0], array_column($letters, 0)))->skip(1)->map(fn ($glyph) => $byGlyph[$glyph]);

        return $similar->reject(fn ($name) => $name === $letter[1])->unique()->prepend($letter[1])->values()->all();
    }

    /** @return array{0: string, 1: string} the joined form and where it sits */
    private static function joinedForm(string $letter): array
    {
        $t = self::TATWEEL;
        $forms = in_array($letter, self::RIGHT_JOINING, true)
            ? [[$t.$letter, 'akhir']]
            : [[$letter.$t, 'awal'], [$t.$letter.$t, 'tengah'], [$t.$letter, 'akhir']];

        return $forms[array_rand($forms)];
    }
}
