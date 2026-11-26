<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\AgeGroup;
use App\Http\Controllers\Controller;
use App\Models\QuizGame;
use App\Models\QuizGameAssignment;
use App\Services\QuizGames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kuiz Interaktif in the teacher portal: the ASUH library, giving a quiz to
 * a class (pupils then see it in Mod Anak) and the class's results.
 */
class QuizGameController extends Controller
{
    public function __construct(private QuizGames $quizzes) {}

    public function index(Request $request): View
    {
        abort_unless($this->quizzes->hasAccess($request->user()), 404);
        $age = AgeGroup::tryFrom((string) $request->query('umur'));

        return view('teacher.quiz-games', [
            'quizzes' => QuizGame::query()->published()->has('questions')->withCount('questions')
                ->when($age && $age !== AgeGroup::Both, fn ($q) => $q->forAge($age))
                ->latest('published_at')->get(),
            'classrooms' => $this->classrooms($request),
            'assignments' => QuizGameAssignment::query()->whereIn('classroom_id', $this->classrooms($request)->pluck('id'))
                ->with(['quizGame', 'classroom'])->withCount(['plays' => fn ($q) => $q->whereNotNull('finished_at')])->latest()->get(),
            'activeAge' => $age,
        ]);
    }

    public function assign(Request $request, QuizGame $quizGame): RedirectResponse
    {
        abort_unless($this->quizzes->hasAccess($request->user()) && $quizGame->isPublished(), 404);

        $data = $request->validate([
            'classroom' => ['required', 'integer', Rule::in($this->classrooms($request)->pluck('id'))],
            'due_at' => ['nullable', 'date', 'after:now'],
        ]);

        $assignment = QuizGameAssignment::query()->updateOrCreate(
            ['quiz_game_id' => $quizGame->id, 'classroom_id' => $data['classroom']],
            ['assigned_by' => $request->user()->id, 'due_at' => filled($data['due_at'] ?? null) ? Date::parse($data['due_at']) : null],
        );

        return redirect()->route('teacher.quiz-games.results', $assignment)->with('status', 'quiz-assigned');
    }

    public function results(Request $request, QuizGameAssignment $assignment): View
    {
        ClassroomController::authorizeClassroom($request, $assignment->classroom);

        return view('teacher.quiz-game-results', [
            'assignment' => $assignment->load('quizGame.questions', 'classroom'),
            'results' => $this->quizzes->classResults($assignment),
            'questions' => $this->quizzes->questionStats($assignment),
        ]);
    }

    public function export(Request $request, QuizGameAssignment $assignment): StreamedResponse
    {
        ClassroomController::authorizeClassroom($request, $assignment->classroom);
        $results = $this->quizzes->classResults($assignment);
        $name = str($assignment->quizGame->title.' '.$assignment->classroom->name)->slug().'.csv';

        return response()->streamDownload(function () use ($results) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Kedudukan', 'Murid', 'Mata', 'Betul', 'Jumlah soalan', 'Tarikh']);
            foreach ($results['ranked'] as $i => $row) {
                fputcsv($out, [$i + 1, $row['child']->nickname, $row['play']->score, $row['play']->correct, $row['play']->total, $row['play']->finished_at->format('Y-m-d H:i')]);
            }
            foreach ($results['waiting'] as $pupil) {
                fputcsv($out, ['', $pupil->nickname, 'Belum main', '', '', '']);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(Request $request, QuizGameAssignment $assignment): RedirectResponse
    {
        ClassroomController::authorizeClassroom($request, $assignment->classroom);
        $assignment->delete();

        return redirect()->route('teacher.quiz-games.index')->with('status', 'quiz-unassigned');
    }

    private function classrooms(Request $request)
    {
        $user = $request->user();

        return $user->institution->classrooms()
            ->when(! $user->isInstitutionAdmin(), fn ($q) => $q->where('teacher_id', $user->id))
            ->orderBy('name')->get();
    }
}
