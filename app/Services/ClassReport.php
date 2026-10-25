<?php

namespace App\Services;

use App\Models\Child;
use App\Models\ChildBadge;
use App\Models\Classroom;
use App\Models\QuizAttempt;
use App\Models\ReadingSession;
use App\Models\VideoView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reading progress per pupil for the teacher: this week (Monday on) and since
 * the pupil was added.
 */
class ClassReport
{
    /**
     * @return Collection<int, array{pupil: Child, week_minutes: int, total_minutes: int, books: int, quizzes: int, badges: int, video_minutes: int, last_read: ?Carbon}>
     */
    public function forClassroom(Classroom $classroom): Collection
    {
        $pupils = $classroom->pupils()->get();
        $ids = $pupils->pluck('id');
        $weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();

        $sessions = ReadingSession::query()->whereIn('child_id', $ids)->get()->groupBy('child_id');
        $quizzes = QuizAttempt::query()->whereIn('child_id', $ids)->where('passed', true)
            ->get()->groupBy('child_id')->map(fn ($rows) => $rows->unique('quiz_id')->count());
        $badges = ChildBadge::query()->whereIn('child_id', $ids)->get()->groupBy('child_id')->map->count();
        $watched = VideoView::query()->whereIn('child_id', $ids)->selectRaw('child_id, SUM(seconds) as total')
            ->groupBy('child_id')->pluck('total', 'child_id');

        return $pupils->map(function ($pupil) use ($sessions, $quizzes, $badges, $watched, $weekStart) {
            $mine = $sessions->get($pupil->id, collect());

            return [
                'pupil' => $pupil,
                'week_minutes' => (int) $mine->filter(fn ($s) => $s->read_on->toDateString() >= $weekStart)->sum('minutes'),
                'total_minutes' => (int) $mine->sum('minutes'),
                'books' => $mine->pluck('book_id')->unique()->count(),
                'quizzes' => (int) $quizzes->get($pupil->id, 0),
                'badges' => (int) $badges->get($pupil->id, 0),
                'video_minutes' => intdiv((int) $watched->get($pupil->id, 0) + 59, 60),
                'last_read' => $mine->max('read_on'),
            ];
        })->sortByDesc('week_minutes')->values();
    }
}
