<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Child;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\ReadingSession;
use App\Services\BadgeAwarder;
use App\Services\ChildMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The book quiz in Mod Anak. Open once the child has read the book in Mod
 * Anak; answers are not revealed, so a retry still needs the book.
 */
class ChildQuizController extends Controller
{
    public function __construct(private ChildMode $childMode) {}

    public function show(Request $request, Book $book): View|RedirectResponse
    {
        [$child, $quiz] = $this->resolve($request, $book);

        if (! $this->hasRead($child, $book)) {
            return redirect()->route('reader.show', $book);
        }

        return view('child-mode.quiz', [
            'child' => $child,
            'book' => $book,
            // Options shuffled per child, the same way each time; answers carry the original index.
            'questions' => $quiz->questions->map(fn (QuizQuestion $question) => [
                'id' => $question->id,
                'text' => $question->question,
                'options' => collect($question->options)->map(fn ($text, $index) => ['index' => $index, 'text' => $text])
                    ->sortBy(fn (array $option) => crc32("{$child->id}-{$question->id}-{$option['index']}"))->values(),
            ]),
        ]);
    }

    public function submit(Request $request, Book $book, BadgeAwarder $awarder): RedirectResponse
    {
        [$child, $quiz] = $this->resolve($request, $book);
        abort_unless($this->hasRead($child, $book), 403);

        $rules = [];
        foreach ($quiz->questions as $question) {
            $rules["answers.{$question->id}"] = ['required', 'integer', 'min:0', 'max:'.(count($question->options) - 1)];
        }
        $answers = $request->validate($rules, ['answers.*.required' => 'Jawab semua soalan dahulu.'])['answers'];

        $score = $quiz->questions->filter(fn (QuizQuestion $q) => (int) $answers[$q->id] === $q->correct_index)->count();
        $total = $quiz->questions->count();

        $child->quizAttempts()->create([
            'quiz_id' => $quiz->id,
            'score' => $score,
            'total' => $total,
            'passed' => Quiz::passes($score, $total),
        ]);

        return redirect()->route('child-mode.quiz.result', $book)->with('quiz_result', [
            'score' => $score,
            'total' => $total,
            'passed' => Quiz::passes($score, $total),
            'badges' => array_map(fn ($badge) => $badge->value, $awarder->check($child)),
        ]);
    }

    public function result(Request $request, Book $book): View|RedirectResponse
    {
        [$child] = $this->resolve($request, $book);
        $result = $request->session()->get('quiz_result');

        if (! $result) {
            return redirect()->route('child-mode.home');
        }

        return view('child-mode.quiz-result', ['child' => $child, 'book' => $book, 'result' => $result]);
    }

    /** @return array{Child, Quiz} */
    private function resolve(Request $request, Book $book): array
    {
        $child = $this->childMode->child($request->user());
        abort_unless($child && $this->childMode->allows($child, $book), 404);

        $quiz = $book->quiz()->with('questions')->first();
        abort_unless($quiz?->isPlayable(), 404);

        return [$child, $quiz];
    }

    private function hasRead(Child $child, Book $book): bool
    {
        return ReadingSession::query()->where('child_id', $child->id)->where('book_id', $book->id)->exists();
    }
}
