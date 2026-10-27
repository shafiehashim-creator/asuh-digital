<?php

namespace App\Support;

use App\Enums\AgeGroup;
use App\Enums\Game;
use App\Models\GameItem;
use Illuminate\Support\Collection;

/**
 * What each Mod Anak game plays with, for the child's age group: the active
 * items the admin keeps at /admin/permainan (started with ASUH's own set,
 * GameItemSeeder), and the hijaiyah letters, which stay in code. Each round
 * draws at random from the pool, so a bigger pool keeps games fresh.
 */
class GameContent
{
    /** @return array<string, mixed> handed to the game's Alpine component as JSON */
    public static function for(Game $game, AgeGroup $age): array
    {
        $older = $age === AgeGroup::TenToThirteen;

        if ($game === Game::Hijaiyah) {
            return ['rounds' => 10, 'questions' => self::hijaiyah($older)];
        }

        $items = self::items($game, $age);

        return match ($game) {
            Game::Memory => ['count' => self::memoryCount($age), 'pairs' => $items->map(fn (GameItem $i) => [$i->prompt, $i->answer])->all()],
            Game::GoodChoice => ['rounds' => 8, 'scenarios' => $items->map(fn (GameItem $i) => ['text' => $i->prompt, 'good' => (bool) $i->is_good, 'why' => (string) $i->explanation])->all()],
            Game::Order => ['rounds' => 3, 'sets' => $items->map(fn (GameItem $i) => ['title' => $i->prompt, 'items' => array_values($i->options ?? [])])->all()],
            Game::WordSearch => [
                'size' => self::gridSize($age), 'count' => $older ? 7 : 5, 'diagonal' => $older,
                'words' => self::fittingWords($items, $age),
            ],
            Game::Prophets => ['rounds' => 8, 'questions' => self::prophets($items)],
            Game::QuickQuiz => ['rounds' => 10, 'seconds' => $older ? 10 : 15, 'questions' => $items->map(fn (GameItem $i) => [
                'prompt' => $i->prompt, 'answer' => $i->answer, 'options' => array_values(array_merge([$i->answer], $i->options ?? [])),
            ])->all()],
        };
    }

    /** The fewest items a game needs to be played (shown below the admin's list). */
    public static function minimum(Game $game, AgeGroup $age): int
    {
        return match ($game) {
            Game::Memory => self::memoryCount($age),
            Game::Order => 1,
            Game::WordSearch => 3,
            Game::Hijaiyah => 0,
            default => 4,
        };
    }

    /** Whether there is enough active content for this age group. */
    public static function playable(Game $game, AgeGroup $age): bool
    {
        if ($game === Game::Hijaiyah) {
            return true;
        }

        $items = self::items($game, $age);
        $count = match ($game) {
            Game::WordSearch => count(self::fittingWords($items, $age)),
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

    private static function memoryCount(AgeGroup $age): int
    {
        return $age === AgeGroup::TenToThirteen ? 8 : 6;
    }

    private static function gridSize(AgeGroup $age): int
    {
        return $age === AgeGroup::TenToThirteen ? 10 : 8;
    }

    /** @return list<string> words that fit the grid, in capitals without spaces */
    private static function fittingWords(Collection $items, AgeGroup $age): array
    {
        return $items->map(fn (GameItem $i) => mb_strtoupper(preg_replace('/[^A-Za-z]/', '', $i->prompt)))
            ->filter(fn (string $word) => strlen($word) >= 3 && strlen($word) <= self::gridSize($age))
            ->unique()->values()->all();
    }

    /** @return list<array{prompt: string, big: string, answer: string, options: list<string>}> */
    private static function hijaiyah(bool $older): array
    {
        $letters = [
            ['ا', 'Alif'], ['ب', 'Ba'], ['ت', 'Ta'], ['ث', 'Tsa'], ['ج', 'Jim'], ['ح', 'Ha'], ['خ', 'Kho'],
            ['د', 'Dal'], ['ذ', 'Zal'], ['ر', 'Ra'], ['ز', 'Zai'], ['س', 'Sin'], ['ش', 'Syin'], ['ص', 'Sod'],
            ['ض', 'Dhod'], ['ط', 'Tho'], ['ظ', 'Zho'], ['ع', 'Ain'], ['غ', 'Ghain'], ['ف', 'Fa'], ['ق', 'Qof'],
            ['ك', 'Kaf'], ['ل', 'Lam'], ['م', 'Mim'], ['ن', 'Nun'], ['و', 'Wau'], ['ه', 'Ha'], ['ي', 'Ya'],
        ];
        $letters = $older ? $letters : array_slice($letters, 0, 14);
        // Two letters are both called "Ha": the game never offers a name twice.
        $names = array_values(array_unique(array_column($letters, 1)));

        return array_map(fn (array $letter) => [
            'prompt' => 'Apakah nama huruf ini?',
            'big' => $letter[0],
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
