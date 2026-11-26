<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Child;
use App\Models\QuizGame;
use App\Models\QuizGameAssignment;
use App\Models\QuizGamePlay;
use App\Models\QuizGameQuestion;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Kuiz Interaktif (user decision 2026-11-26, like Wayground): ASUH admins
 * make the quizzes; children in Mod Anak and their parents play them with a
 * family subscription, and pupils play the ones their teacher assigns to
 * their class. Answers are checked here, never in the browser, so scores
 * and class leaderboards can be trusted. Hidden until `quiz_games_open`.
 */
class QuizGames
{
    /** Points for a right answer: half for being right, half for being quick. */
    public const BASE_POINTS = 500;

    public const SPEED_POINTS = 500;

    /** Extra points for each answer in a row after the first, up to five. */
    public const STREAK_POINTS = 100;

    public function isOpen(): bool
    {
        return (bool) Setting::get('quiz_games_open');
    }

    /** Who is playing: the child in Mod Anak, or else the signed-in adult. */
    public function player(?User $user, ?Child $child): Child|User|null
    {
        return $child ?? $user;
    }

    public function hasAccess(Child|User|null $player): bool
    {
        if ($player instanceof Child) {
            if ($player->isPupil()) {
                return $this->isOpen();
            }

            return $player->user?->isStaff() || ($this->isOpen() && $player->user?->passes()->active()->exists());
        }

        if (! $player) {
            return false;
        }
        if ($player->isStaff()) {
            return true;
        }

        return $this->isOpen() && ($player->isInstitutionStaff() || $player->passes()->active()->exists());
    }

    /** Quizzes a player may open: pupils get their class's, everyone else the published ones (by age for a child). */
    public function available(Child|User $player): Collection
    {
        if ($player instanceof Child && $player->isPupil()) {
            return $this->assignmentsFor($player)->map(fn (QuizGameAssignment $a) => $a->quizGame->setRelation('assignment', $a));
        }

        return QuizGame::query()->published()
            ->when($player instanceof Child, fn ($q) => $q->forAge($player->age_group))
            ->has('questions')->withCount('questions')
            ->latest('published_at')->get();
    }

    /** @return Collection<int, QuizGameAssignment> */
    public function assignmentsFor(Child $pupil): Collection
    {
        return QuizGameAssignment::query()->where('classroom_id', $pupil->classroom_id)
            ->whereHas('quizGame', fn ($q) => $q->published())
            ->with(['quizGame' => fn ($q) => $q->withCount('questions')])
            ->latest()->get();
    }

    public function canPlay(Child|User $player, QuizGame $quiz): bool
    {
        if (! $this->hasAccess($player)) {
            return false;
        }
        $staff = $player instanceof User ? $player->isStaff() : (bool) $player->user?->isStaff();
        if (! $quiz->isPublished() && ! $staff) {
            return false;
        }
        if ($player instanceof Child && $player->isPupil()) {
            return $this->assignment($player, $quiz) !== null;
        }

        return true;
    }

    public function assignment(Child $pupil, QuizGame $quiz): ?QuizGameAssignment
    {
        return $pupil->isPupil()
            ? QuizGameAssignment::query()->where('classroom_id', $pupil->classroom_id)->where('quiz_game_id', $quiz->id)->first()
            : null;
    }

    public function start(Child|User $player, QuizGame $quiz): QuizGamePlay
    {
        return QuizGamePlay::query()->create([
            'quiz_game_id' => $quiz->id,
            'child_id' => $player instanceof Child ? $player->id : null,
            'user_id' => $player instanceof User ? $player->id : null,
            'assignment_id' => $player instanceof Child ? $this->assignment($player, $quiz)?->id : null,
            'total' => $quiz->questions()->count(),
        ]);
    }

    public function owns(Child|User $player, QuizGamePlay $play): bool
    {
        return $player instanceof Child ? $play->child_id === $player->id : $play->user_id === $player->id;
    }

    /**
     * What the browser gets: no answers. Items to put in order come shuffled.
     *
     * @return list<array<string, mixed>>
     */
    public function questionsForPlay(QuizGame $quiz): array
    {
        return $quiz->questions->map(fn (QuizGameQuestion $q) => [
            'id' => $q->id,
            'type' => $q->type->value,
            'prompt' => $q->prompt,
            'image' => $q->image_path ? Storage::disk('public')->url($q->image_path) : null,
            'seconds' => $q->seconds ?: $quiz->seconds_per_question,
            'options' => match ($q->type) {
                QuestionType::Choice => array_column($q->options ?? [], 't'),
                QuestionType::Order => $this->shuffled(array_column($q->options ?? [], 't')),
                default => [],
            },
        ])->values()->all();
    }

    /**
     * Checks one answer and adds its points. A question counts once.
     *
     * @return array{correct: bool, points: int, score: int, streak: int, answer: string|list<string>, explanation: ?string}
     */
    public function answer(QuizGamePlay $play, QuizGameQuestion $question, mixed $given, int $ms): array
    {
        if ($question->quiz_game_id !== $play->quiz_game_id || $play->finished_at) {
            throw new InvalidArgumentException('This question is not part of an open play.');
        }

        return DB::transaction(function () use ($play, $question, $given, $ms) {
            $play = QuizGamePlay::query()->lockForUpdate()->findOrFail($play->id);
            $answers = $play->answers ?? [];
            $key = (string) $question->id;

            if (! isset($answers[$key])) {
                $correct = $this->isCorrect($question, $given);
                $limit = max(5, $question->seconds ?: $play->quizGame->seconds_per_question) * 1000;
                $ms = min(max($ms, 0), $limit);
                $streak = $correct ? $this->streak($answers) + 1 : 0;
                $points = $correct
                    ? self::BASE_POINTS + (int) round(self::SPEED_POINTS * (1 - $ms / $limit)) + self::STREAK_POINTS * min(max($streak - 1, 0), 5)
                    : 0;

                $answers[$key] = ['ok' => $correct, 'pts' => $points, 'ms' => $ms, 'streak' => $streak];
                $play->forceFill([
                    'answers' => $answers,
                    'score' => $play->score + $points,
                    'correct' => $play->correct + ($correct ? 1 : 0),
                ])->save();
            }

            $mine = $answers[$key];

            return [
                'correct' => $mine['ok'],
                'points' => $mine['pts'],
                'score' => $play->score,
                'streak' => $mine['streak'],
                'answer' => $this->rightAnswer($question),
                'explanation' => $question->explanation,
            ];
        });
    }

    public function finish(QuizGamePlay $play): QuizGamePlay
    {
        if (! $play->finished_at) {
            $play->forceFill(['finished_at' => now()])->save();
        }

        return $play;
    }

    public function isCorrect(QuizGameQuestion $question, mixed $given): bool
    {
        return match ($question->type) {
            QuestionType::Choice => is_numeric($given) && (bool) (($question->options ?? [])[(int) $given]['ok'] ?? false),
            QuestionType::TrueFalse => is_bool($given) && $given === (bool) ($question->answer[0] ?? false),
            QuestionType::Order => is_array($given) && array_map(fn ($v) => (string) $v, array_values($given)) === array_column($question->options ?? [], 't'),
            QuestionType::Fill => is_string($given) && in_array(self::normal($given), array_map(fn ($a) => self::normal((string) $a), $question->answer ?? []), true),
        };
    }

    /** @return string|list<string> the right answer, to show after answering */
    public function rightAnswer(QuizGameQuestion $question): string|array
    {
        return match ($question->type) {
            QuestionType::Choice => collect($question->options ?? [])->firstWhere('ok', true)['t'] ?? '',
            QuestionType::TrueFalse => ($question->answer[0] ?? false) ? 'Betul' : 'Salah',
            QuestionType::Order => array_column($question->options ?? [], 't'),
            QuestionType::Fill => (string) (($question->answer ?? [])[0] ?? ''),
        };
    }

    /**
     * Best finished go of each pupil in the class, highest first, and who has not played.
     *
     * @return array{ranked: Collection<int, array{child: Child, play: QuizGamePlay}>, waiting: Collection<int, Child>}
     */
    public function classResults(QuizGameAssignment $assignment): array
    {
        $pupils = $assignment->classroom->pupils()->orderBy('nickname')->get();
        $best = $assignment->plays()->whereNotNull('finished_at')->get()
            ->groupBy('child_id')->map(fn (Collection $plays) => $plays->sortByDesc('score')->first());

        $ranked = $pupils->filter(fn (Child $p) => $best->has($p->id))
            ->map(fn (Child $p) => ['child' => $p, 'play' => $best[$p->id]])
            ->sortByDesc(fn ($row) => $row['play']->score)->values();

        return ['ranked' => $ranked, 'waiting' => $pupils->reject(fn (Child $p) => $best->has($p->id))->values()];
    }

    /**
     * Share of answers that were right for each question, across the class.
     *
     * @return array<int, array{question: QuizGameQuestion, right: int, answered: int}>
     */
    public function questionStats(QuizGameAssignment $assignment): array
    {
        $plays = $assignment->plays()->whereNotNull('finished_at')->get();

        return $assignment->quizGame->questions->map(function (QuizGameQuestion $q) use ($plays) {
            $given = $plays->map(fn (QuizGamePlay $p) => ($p->answers ?? [])[(string) $q->id] ?? null)->filter();

            return ['question' => $q, 'right' => $given->where('ok', true)->count(), 'answered' => $given->count()];
        })->all();
    }

    /** The player's best score so far for each quiz id. */
    public function bestScores(Child|User $player): array
    {
        return QuizGamePlay::query()
            ->where($player instanceof Child ? 'child_id' : 'user_id', $player->id)
            ->whereNotNull('finished_at')
            ->groupBy('quiz_game_id')->selectRaw('quiz_game_id, max(score) as best')
            ->pluck('best', 'quiz_game_id')->map(fn ($v) => (int) $v)->all();
    }

    private function streak(array $answers): int
    {
        $streak = 0;
        foreach (array_reverse($answers) as $answer) {
            if (! $answer['ok']) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    /** @param  list<string>  $items */
    private function shuffled(array $items): array
    {
        if (count($items) < 2) {
            return $items;
        }
        do {
            $copy = $items;
            shuffle($copy);
        } while ($copy === $items);

        return $copy;
    }

    private static function normal(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}\s]/u', '', Str::lower($text))));
    }
}
