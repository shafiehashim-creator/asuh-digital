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
    /**
     * Pairs that need knowing, not just matching pictures (Padan Kad, Sukar).
     * Every answer is different, so a card never fits two others.
     *
     * @var list<array{0: string, 1: string, 2: bool}> card, its pair, older children only
     */
    private const KNOWLEDGE_PAIRS = [
        ['Bismillah', 'Sebelum makan', false], ['Alhamdulillah', 'Selepas makan', false],
        ['Assalamualaikum', 'Ucapan ketika berjumpa', false], ['Insya-Allah', 'Ketika berjanji', false],
        ['Subuh', '2 rakaat', false], ['Maghrib', '3 rakaat', false], ['Isyak', '4 rakaat', false],
        ['Ramadan', 'Bulan berpuasa', false], ['Syawal', 'Hari Raya Aidilfitri', false],
        ['Kaabah', 'Kiblat umat Islam', false], ['Wuduk', 'Bersuci sebelum solat', false],
        ['Nabi Musa', 'Kitab Taurat', true], ['Nabi Daud', 'Kitab Zabur', true], ['Nabi Isa', 'Kitab Injil', true],
        ['Makkah', 'Kota kelahiran Nabi ﷺ', true], ['Madinah', 'Kota hijrah Nabi ﷺ', true],
        ['Muharam', 'Bulan pertama Hijrah', true], ['Zulhijah', 'Bulan ibadah haji', true],
        ['Haji', 'Rukun Islam kelima', true], ['Zakat', 'Rukun Islam keempat', true],
    ];

    /**
     * What the game plays with, handed to its Alpine component as JSON. Each
     * level is a different kind of thinking, not only more of the same; a
     * level may be played by another component ('component').
     *
     * @return array<string, mixed>
     */
    public static function for(Game $game, AgeGroup $age, int $level = 1): array
    {
        $older = $age === AgeGroup::TenToThirteen;
        $level = max(1, min(3, $level));
        // One value per level, for the younger or the older group.
        $pick = fn (array $young, array $old) => ($older ? $old : $young)[$level - 1];

        return match ($game) {
            Game::Memory => self::memory($age, $level),
            Game::GoodChoice => self::goodChoice($age, $level),
            Game::Order => [
                'rounds' => $pick([2, 3, 3], [2, 3, 3]),
                'impostor' => $level >= 2,
                'reverse' => $level === 3,
                'sets' => self::items($game, $age)->map(fn (GameItem $i) => ['title' => $i->prompt, 'items' => array_values($i->options ?? [])])->all(),
            ],
            Game::Hijaiyah => ['rounds' => 10, 'seconds' => $pick([null, null, null], [null, 12, 10]), 'questions' => HijaiyahQuestions::make($older, $level)],
            Game::WordSearch => self::wordSearch($age, $level),
            Game::Prophets => [
                'rounds' => $pick([6, 8, 10], [8, 8, 12]),
                'seconds' => $pick([null, null, 25], [null, null, 15]),
                'questions' => ProphetQuestions::make(self::items($game, $age), $level),
            ],
            Game::QuickQuiz => [
                'rounds' => $level === 3 ? null : $pick([10, 12, 0], [10, 12, 0]),
                'seconds' => $pick([20, 15, 10], [15, 10, 6]),
                'lives' => $level >= 2 ? 3 : null,
                // Sukar keeps going until three answers are missed.
                'survival' => $level === 3 ? 15 : null,
                'questions' => self::quiz(self::items($game, $age)),
            ],
            Game::Science => match ($level) {
                1 => ['rounds' => 8, 'questions' => self::quiz(self::items($game, $age))],
                2 => ['rounds' => 8, 'questions' => ScienceQuestions::oddOneOut($older, 16)],
                default => ['rounds' => 12, 'seconds' => $older ? 5 : 8, 'lives' => 3, 'questions' => ScienceQuestions::sorting($older, 20)],
            },
            Game::Math => ['rounds' => 10, 'seconds' => $pick([null, 25, 30], [null, 20, 25]), 'questions' => MathQuestions::make($older, $level, 30)],
            Game::Pattern => ['rounds' => 8, 'seconds' => $pick([null, null, null], [null, null, 30]), 'questions' => PatternQuestions::make($older, $level, 24)],
            Game::Sliding => [
                'size' => $pick([3, 3, 4], [3, 4, 5]),
                'shuffle' => $pick([12, 40, 120], [40, 120, 300]),
                'numbers' => $level === 1,
                'images' => self::puzzleImages(),
            ],
            Game::Sudoku => [
                'size' => $pick([4, 4, 6], [4, 6, 6]),
                'blanks' => $pick([5, 9, 10], [9, 14, 22]),
                // Sukar: no hint when a picture is wrong; check the whole grid at the end.
                'checkLater' => $level === 3,
                'symbols' => ['🌙', '⭐', '🕌', '📖', '🌴', '🐪'],
            ],
        };
    }

    /** @return array<string, mixed> */
    private static function memory(AgeGroup $age, int $level): array
    {
        $older = $age === AgeGroup::TenToThirteen;

        if ($level === 3) {
            return [
                'count' => self::memoryCount($age, 3),
                'preview' => 0,
                'pairs' => collect(self::KNOWLEDGE_PAIRS)->filter(fn (array $p) => $older || ! $p[2])->map(fn (array $p) => [$p[0], $p[1]])->values()->all(),
                'words' => true,
            ];
        }

        return [
            'count' => self::memoryCount($age, $level),
            // Mudah shows every card for a moment before turning them over.
            'preview' => $level === 1 ? 3 : 0,
            'pairs' => self::items(Game::Memory, $age)->map(fn (GameItem $i) => [$i->prompt, $i->answer])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function goodChoice(AgeGroup $age, int $level): array
    {
        $variant = [2 => 'tindakan', 3 => 'cerita'][$level] ?? null;
        $items = $variant ? self::items(Game::GoodChoice, $age, $variant) : collect();

        if ($variant === 'tindakan' && $items->count() >= 4) {
            return ['component' => 'gameQuiz', 'rounds' => 6, 'questions' => $items->map(fn (GameItem $i) => [
                'prompt' => $i->prompt, 'big' => null, 'answer' => $i->answer,
                'options' => array_values(array_merge([$i->answer], $i->options ?? [])), 'why' => $i->explanation,
            ])->all()];
        }

        if ($variant === 'cerita' && $items->count() >= 4) {
            return ['component' => 'gameQuiz', 'rounds' => 5, 'questions' => $items->map(fn (GameItem $i) => [
                'prompt' => "{$i->prompt}: ayat manakah bisikan penggoda?", 'big' => null, 'answer' => $i->answer,
                'options' => array_values($i->options ?? []), 'keepOrder' => true, 'why' => $i->explanation,
            ])->all()];
        }

        $scenarios = self::items(Game::GoodChoice, $age);

        return ['rounds' => 8, 'scenarios' => $scenarios->map(fn (GameItem $i) => ['text' => $i->prompt, 'good' => (bool) $i->is_good, 'why' => (string) $i->explanation])->all()];
    }

    /** @return array<string, mixed> */
    private static function wordSearch(AgeGroup $age, int $level): array
    {
        $older = $age === AgeGroup::TenToThirteen;
        $items = self::items(Game::WordSearch, $age);
        $words = self::fittingWords($items, $age, $level);
        $clues = $items->filter(fn (GameItem $i) => filled($i->explanation))
            ->mapWithKeys(fn (GameItem $i) => [mb_strtoupper(preg_replace('/[^A-Za-z]/', '', $i->prompt)) => $i->explanation]);

        // Sukar hides the words: only their clues are shown.
        $useClues = $level === 3 && collect($words)->filter(fn ($w) => $clues->has($w))->count() >= 4;

        return [
            'size' => self::gridSize($age, $level),
            'count' => $level === 3 ? ($older ? 6 : 4) : ($older ? [5, 7][$level - 1] : [4, 5][$level - 1]),
            'diagonal' => $level >= 2,
            'backwards' => $level >= 2,
            'words' => $useClues ? collect($words)->filter(fn ($w) => $clues->has($w))->values()->all() : $words,
            'clues' => $useClues ? $clues->only($words)->all() : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function quiz(Collection $items): array
    {
        return $items->map(fn (GameItem $i) => [
            'prompt' => $i->prompt, 'big' => null, 'answer' => $i->answer, 'options' => array_values(array_merge([$i->answer], $i->options ?? [])),
        ])->all();
    }

    /** The fewest items a game needs to be played (shown at the top of the admin's list). */
    public static function minimum(Game $game, AgeGroup $age): int
    {
        return match ($game) {
            // Enough picture pairs for Sederhana (Sukar plays knowledge pairs).
            Game::Memory => self::memoryCount($age, 2),
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
    private static function items(Game $game, AgeGroup $age, ?string $variant = null): Collection
    {
        return GameItem::query()
            ->where('game', $game)
            ->where(fn ($q) => $variant ? $q->where('variant', $variant) : $q->whereNull('variant'))
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
}
