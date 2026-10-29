<?php

namespace App\Support;

use App\Models\GameItem;
use Illuminate\Support\Collection;

/**
 * Teka Nabi dan Rasul at each level: guess the prophet from a clue (Mudah),
 * pick the clue that belongs to a prophet (Sederhana), and at Sukar also say
 * which prophet came first, from prophets whose order is clear.
 */
class ProphetQuestions
{
    /** In the order they were sent, kept to prophets whose order is beyond doubt. */
    private const IN_ORDER = ['Nabi Adam', 'Nabi Nuh', 'Nabi Ibrahim', 'Nabi Ismail', 'Nabi Yusuf', 'Nabi Musa', 'Nabi Daud', 'Nabi Sulaiman', 'Nabi Zakaria', 'Nabi Isa', 'Nabi Muhammad ﷺ'];

    /**
     * @param  Collection<int, GameItem>  $items  clue and prophet from the admin
     * @return list<array<string, mixed>>
     */
    public static function make(Collection $items, int $level): array
    {
        $names = $items->pluck('answer')->unique()->values()->all();
        $guess = $items->map(fn (GameItem $i) => ['prompt' => $i->prompt, 'big' => null, 'answer' => $i->answer, 'options' => $names])->all();

        if ($level === 1) {
            return $guess;
        }

        // One clue per prophet, among clues of other prophets.
        $whichClue = $items->groupBy('answer')->map(function (Collection $clues, string $name) use ($items, $level) {
            $clue = $clues->random()->prompt;
            $others = $items->where('answer', '!=', $name)->shuffle()->take($level === 3 ? 3 : 2)->pluck('prompt');

            return ['prompt' => "Petunjuk manakah tentang {$name}?", 'big' => null, 'answer' => $clue, 'options' => $others->prepend($clue)->values()->all()];
        })->values();

        if ($level === 2) {
            return $whichClue->all();
        }

        return $whichClue->merge(self::order(12))->shuffle()->values()->all();
    }

    /** @return list<array<string, mixed>> "which came first / last?" */
    private static function order(int $count): array
    {
        $questions = [];
        for ($i = 0; $i < $count; $i++) {
            $picked = collect(self::IN_ORDER)->shuffle()->take(random_int(0, 1) ? 2 : 3)->all();
            $positions = array_map(fn ($name) => array_search($name, self::IN_ORDER, true), $picked);
            $first = random_int(0, 2) > 0;
            $answer = $picked[array_search($first ? min($positions) : max($positions), $positions, true)];

            $questions[] = [
                'prompt' => $first ? 'Antara nabi ini, siapakah yang diutus PALING AWAL?' : 'Antara nabi ini, siapakah yang diutus PALING AKHIR?',
                'big' => null,
                'answer' => $answer,
                'options' => $picked,
                'keepOrder' => true,
            ];
        }

        return collect($questions)->unique(fn ($q) => $q['prompt'].implode(',', $q['options']))->values()->all();
    }
}
