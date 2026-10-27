<?php

namespace App\Support;

use App\Enums\AgeGroup;
use App\Enums\Game;
use App\Models\GameItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Vite;

/**
 * What each Mod Anak game plays with, for the child's age group and level
 * (1 Mudah, 2 Sederhana, 3 Sukar): the active items the admin keeps at
 * /admin/permainan (started with ASUH's own set, GameItemSeeder), and what is
 * made in code: hijaiyah letters, sums, patterns and the two puzzles. Each
 * round draws at random from the pool, so a bigger pool keeps games fresh.
 */
class GameContent
{
    /** @return array<string, mixed> handed to the game's Alpine component as JSON */
    public static function for(Game $game, AgeGroup $age, int $level = 1): array
    {
        $older = $age === AgeGroup::TenToThirteen;
        $level = max(1, min(3, $level));
        // One value per level, for the younger or the older group.
        $pick = fn (array $young, array $old) => ($older ? $old : $young)[$level - 1];

        if (! $game->editable()) {
            return match ($game) {
                Game::Hijaiyah => ['rounds' => 10, 'seconds' => $pick([null, null, 12], [null, 10, 6]), 'questions' => self::hijaiyah($older, $level)],
                Game::Math => ['rounds' => 10, 'seconds' => $pick([null, 20, 15], [null, 15, 10]), 'questions' => MathQuestions::make($older, $level, 30)],
                Game::Pattern => ['rounds' => 8, 'seconds' => $pick([null, null, 20], [null, 20, 15]), 'questions' => PatternQuestions::make($older, $level, 24)],
                Game::Sliding => [
                    'size' => $pick([3, 3, 4], [3, 4, 4]),
                    'shuffle' => $pick([12, 40, 50], [40, 60, 150]),
                    'numbers' => $level === 1,
                    'images' => self::puzzleImages(),
                ],
                Game::Sudoku => [
                    'size' => $pick([4, 4, 4], [4, 6, 6]),
                    'blanks' => $pick([5, 8, 10], [9, 14, 20]),
                    'symbols' => ['🌙', '⭐', '🕌', '📖', '🌴', '🐪'],
                ],
            };
        }

        $items = self::items($game, $age);

        return match ($game) {
            Game::Memory => ['count' => self::memoryCount($age, $level), 'pairs' => $items->map(fn (GameItem $i) => [$i->prompt, $i->answer])->all()],
            Game::GoodChoice => ['rounds' => $pick([6, 8, 10], [6, 8, 10]), 'scenarios' => $items->map(fn (GameItem $i) => ['text' => $i->prompt, 'good' => (bool) $i->is_good, 'why' => (string) $i->explanation])->all()],
            Game::Order => ['rounds' => $pick([2, 3, 4], [2, 3, 4]), 'sets' => $items->map(fn (GameItem $i) => ['title' => $i->prompt, 'items' => array_values($i->options ?? [])])->all()],
            Game::WordSearch => [
                'size' => self::gridSize($age, $level), 'count' => $pick([4, 5, 6], [5, 7, 8]),
                'diagonal' => $pick([false, false, true], [false, true, true]),
                'backwards' => $pick([false, false, false], [false, false, true]),
                'words' => self::fittingWords($items, $age, $level),
            ],
            Game::Prophets => ['rounds' => $pick([6, 8, 10], [6, 8, 10]), 'seconds' => $pick([null, null, 20], [null, null, 15]), 'questions' => self::prophets($items)],
            Game::QuickQuiz, Game::Science => [
                'rounds' => $game === Game::Science ? $pick([6, 8, 10], [6, 8, 10]) : 10,
                'seconds' => $game === Game::Science ? $pick([null, null, 20], [null, null, 15]) : $pick([20, 15, 10], [15, 10, 7]),
                'questions' => $items->map(fn (GameItem $i) => [
                    'prompt' => $i->prompt, 'answer' => $i->answer, 'options' => array_values(array_merge([$i->answer], $i->options ?? [])),
                ])->all(),
            ],
        };
    }

    /** The fewest items a game needs to be played (shown at the top of the admin's list). */
    public static function minimum(Game $game, AgeGroup $age): int
    {
        return match ($game) {
            // Enough pairs for the hardest level.
            Game::Memory => self::memoryCount($age, 3),
            Game::Order => 1,
            Game::WordSearch => 3,
            Game::Hijaiyah, Game::Math, Game::Pattern, Game::Sliding, Game::Sudoku => 0,
            default => 4,
        };
    }

    /** Whether there is enough active content for this age group. */
    public static function playable(Game $game, AgeGroup $age): bool
    {
        if (! $game->editable()) {
            return true;
        }

        $items = self::items($game, $age);
        $count = match ($game) {
            Game::WordSearch => count(self::fittingWords($items, $age, 1)),
            // Every question needs other names to choose from.
            Game::Prophets => $items->pluck('answer')->unique()->count(),
            default => $items->count(),
        };

        return $count >= self::minimum($game, $age);
    }

    /** @return Collection<int, GameItem> */
    private static function items(Game $game, AgeGroup $age): Collection
    {
        return GameItem::query()
            ->where('game', $game)
            ->where('is_active', true)
            ->whereIn('age_group', [$age->value, AgeGroup::Both->value])
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    private static function memoryCount(AgeGroup $age, int $level): int
    {
        return ($age === AgeGroup::TenToThirteen ? [6, 8, 10] : [4, 6, 8])[$level - 1];
    }

    private static function gridSize(AgeGroup $age, int $level): int
    {
        return ($age === AgeGroup::TenToThirteen ? [8, 10, 10] : [7, 8, 8])[$level - 1];
    }

    /** @return list<string> words that fit the grid, in capitals without spaces */
    private static function fittingWords(Collection $items, AgeGroup $age, int $level): array
    {
        return $items->map(fn (GameItem $i) => mb_strtoupper(preg_replace('/[^A-Za-z]/', '', $i->prompt)))
            ->filter(fn (string $word) => strlen($word) >= 3 && strlen($word) <= self::gridSize($age, $level))
            ->unique()->values()->all();
    }

    /** @return list<string> square pictures of Anis and Anas for the sliding puzzle */
    private static function puzzleImages(): array
    {
        return array_map(fn (int $n) => Vite::asset("resources/images/games/puzzle-{$n}.webp"), [1, 2, 3]);
    }

    /** @return list<array{prompt: string, big: string, answer: string, options: list<string>}> */
    private static function hijaiyah(bool $older, int $level): array
    {
        $letters = [
            ['ا', 'Alif'], ['ب', 'Ba'], ['ت', 'Ta'], ['ث', 'Tsa'], ['ج', 'Jim'], ['ح', 'Ha'], ['خ', 'Kho'],
            ['د', 'Dal'], ['ذ', 'Zal'], ['ر', 'Ra'], ['ز', 'Zai'], ['س', 'Sin'], ['ش', 'Syin'], ['ص', 'Sod'],
            ['ض', 'Dhod'], ['ط', 'Tho'], ['ظ', 'Zho'], ['ع', 'Ain'], ['غ', 'Ghain'], ['ف', 'Fa'], ['ق', 'Qof'],
            ['ك', 'Kaf'], ['ل', 'Lam'], ['م', 'Mim'], ['ن', 'Nun'], ['و', 'Wau'], ['ه', 'Ha'], ['ي', 'Ya'],
        ];
        $letters = $older || $level === 3 ? $letters : array_slice($letters, 0, $level === 1 ? 10 : 14);
        // Two letters are both called "Ha": the game never offers a name twice.
        $names = array_values(array_unique(array_column($letters, 1)));

        return array_map(fn (array $letter) => [
            'prompt' => 'Apakah nama huruf ini?',
            'big' => $letter[0],
            'rtl' => true,
            'answer' => $letter[1],
            'options' => $names,
        ], $letters);
    }

    /** @return list<array{prompt: string, answer: string, options: list<string>}> */
    private static function prophets(Collection $items): array
    {
        $names = $items->pluck('answer')->unique()->values()->all();

        return $items->map(fn (GameItem $i) => ['prompt' => $i->prompt, 'answer' => $i->answer, 'options' => $names])->all();
    }
}
