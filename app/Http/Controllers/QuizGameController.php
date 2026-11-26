<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\QuizGame;
use App\Models\QuizGamePlay;
use App\Models\User;
use App\Services\ChildMode;
use App\Services\QuizGames;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Playing a Kuiz Interaktif: a child in Mod Anak (family or pupil), or a
 * signed-in parent, teacher or staff member trying it. Answers go to the
 * server one at a time and are checked there.
 */
class QuizGameController extends Controller
{
    public function __construct(private ChildMode $childMode, private QuizGames $quizzes) {}

    public function index(Request $request): View
    {
        [$player, $child] = $this->player($request);

        return view('quiz-games.index', [
            'child' => $child,
            'quizzes' => $this->quizzes->available($player),
            'best' => $this->quizzes->bestScores($player),
        ]);
    }

    public function show(Request $request, QuizGame $quizGame): View
    {
        [$player, $child] = $this->player($request);
        abort_unless($this->quizzes->canPlay($player, $quizGame), 404);
        abort_if($quizGame->questions()->doesntExist(), 404);

        $assignment = $child ? $this->quizzes->assignment($child, $quizGame) : null;

        return view('quiz-games.show', [
            'child' => $child,
            'quiz' => $quizGame->loadCount('questions'),
            'assignment' => $assignment,
            'best' => $this->quizzes->bestScores($player)[$quizGame->id] ?? null,
            'config' => [
                'startUrl' => route('child-mode.quiz-games.start', $quizGame),
                'csrf' => csrf_token(),
                'backUrl' => $child ? route('child-mode.home') : route('child-mode.quiz-games.index'),
            ],
        ]);
    }

    public function start(Request $request, QuizGame $quizGame): JsonResponse
    {
        [$player] = $this->player($request);
        abort_unless($this->quizzes->canPlay($player, $quizGame), 404);

        $play = $this->quizzes->start($player, $quizGame);
        $quizGame->load('questions');

        return response()->json([
            'answerUrl' => route('child-mode.quiz-games.answer', $play),
            'finishUrl' => route('child-mode.quiz-games.finish', $play),
            'questions' => $this->quizzes->questionsForPlay($quizGame),
        ]);
    }

    public function answer(Request $request, QuizGamePlay $play): JsonResponse
    {
        [$player] = $this->player($request);
        abort_unless($this->quizzes->owns($player, $play) && ! $play->finished_at, 404);

        $data = $request->validate([
            'question' => ['required', 'integer', Rule::exists('quiz_game_questions', 'id')->where('quiz_game_id', $play->quiz_game_id)],
            'answer' => ['nullable'],
            'ms' => ['required', 'integer', 'min:0', 'max:600000'],
        ]);

        $question = $play->quizGame->questions()->findOrFail($data['question']);

        return response()->json($this->quizzes->answer($play, $question, $data['answer'] ?? null, (int) $data['ms']));
    }

    public function finish(Request $request, QuizGamePlay $play): JsonResponse
    {
        [$player, $child] = $this->player($request);
        abort_unless($this->quizzes->owns($player, $play), 404);

        $play = $this->quizzes->finish($play);
        $rank = null;
        if ($play->assignment) {
            $ranked = $this->quizzes->classResults($play->assignment)['ranked'];
            $at = $ranked->search(fn ($row) => $row['child']->id === $child?->id);
            $rank = $at === false ? null : ['place' => $at + 1, 'of' => $ranked->count()];
        }

        return response()->json([
            'score' => $play->score,
            'correct' => $play->correct,
            'total' => $play->total,
            'best' => $this->quizzes->bestScores($player)[$play->quiz_game_id] ?? $play->score,
            'rank' => $rank,
        ]);
    }

    /** @return array{0: Child|User, 1: ?Child} */
    private function player(Request $request): array
    {
        $child = $this->childMode->child($request->user());
        $player = $this->quizzes->player($request->user(), $child);
        abort_unless($player && $this->quizzes->hasAccess($player), 404);

        return [$player, $child];
    }
}
