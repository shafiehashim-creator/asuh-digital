<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Child;
use App\Models\Quiz;
use App\Services\ChildMode;
use App\Services\ParentGate;
use App\Services\ReadingLog;
use App\Services\VideoWatching;
use App\Support\BrandVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChildModeController extends Controller
{
    public function __construct(private ChildMode $childMode) {}

    /** The parent hands the device over: lock the session to this child. */
    public function enter(Request $request, Child $child): RedirectResponse
    {
        abort_unless($child->user_id === $request->user()->id, 404);

        $this->childMode->enter($child);

        // "Sambung dalam Mod Anak" from the account summary opens the book straight away.
        $book = $request->filled('buku') ? Book::query()->where('slug', $request->string('buku'))->first() : null;

        if ($book && $this->childMode->allows($child, $book)) {
            return redirect()->route('reader.show', $book);
        }

        return redirect()->route('child-mode.home');
    }

    public function home(Request $request, ReadingLog $log, VideoWatching $watching): View|RedirectResponse
    {
        $child = $this->childMode->child($request->user());

        if (! $child) {
            return redirect()->route('account');
        }

        return view('child-mode.home', [
            'child' => $child,
            'books' => $this->childMode->shelf($child),
            'badges' => $child->badges()->get(),
            // Phase 2 animated videos, each with the reason it is locked (if any).
            'videos' => $watching->hasAccess($child)
                ? $watching->shelf($child)->map(fn ($video) => ['video' => $video, 'locked' => $watching->lockReason($child, $video)])
                : collect(),
            'videoSecondsLeft' => $watching->hasAccess($child) ? $watching->secondsLeftToday($child) : null,
            // Books with a quiz the child has not passed yet.
            'quizBookIds' => Quiz::query()->where('is_active', true)->has('questions', '>=', Quiz::MIN_QUESTIONS)
                ->whereDoesntHave('attempts', fn ($q) => $q->where('child_id', $child->id)->where('passed', true))
                ->pluck('book_id')->all(),
            // Only books still on the shelf (a lapsed pass or a new age group drops some).
            'recent' => $log->recent($child, 12)
                ->filter(fn ($session) => $session->book && $this->childMode->allows($child, $session->book))
                ->take(4)
                ->values(),
        ]);
    }

    /**
     * The ASUH music video for every child, on ASUH's own player (no links
     * out to YouTube). Short and free, so it does not count towards the
     * daily viewing limit.
     */
    public function song(Request $request): View|RedirectResponse
    {
        $child = $this->childMode->child($request->user());

        if (! $child || ! BrandVideo::shownOn('child_mode')) {
            return redirect()->route('child-mode.home');
        }

        return view('child-mode.song', [
            'child' => $child,
            'embed' => ['provider' => 'youtube', 'id' => BrandVideo::id(), 'url' => null],
        ]);
    }

    public function badges(Request $request): View|RedirectResponse
    {
        $child = $this->childMode->child($request->user());

        if (! $child) {
            return redirect()->route('account');
        }

        return view('child-mode.badges', [
            'child' => $child,
            'earned' => $child->badges()->get()->keyBy(fn ($row) => $row->badge->value),
        ]);
    }

    public function gate(Request $request, ParentGate $gate): View|RedirectResponse
    {
        if (! $this->childMode->isActive()) {
            return redirect()->route('account');
        }

        $parent = $request->user();

        return view('child-mode.gate', [
            'usesPin' => $parent->hasParentPin(),
            'question' => $parent->hasParentPin() ? null : $gate->newQuestion(),
        ]);
    }

    public function leave(Request $request, ParentGate $gate): RedirectResponse
    {
        $request->validate(['answer' => ['required', 'string', 'max:10']], [], ['answer' => 'jawapan']);

        if (! $gate->passes($request->user(), $request->string('answer'))) {
            return redirect()->route('child-mode.gate')
                ->withErrors(['answer' => $request->user()->hasParentPin() ? 'PIN tidak tepat.' : 'Jawapan tidak tepat. Cuba soalan baharu ini.']);
        }

        $this->childMode->leave();

        return redirect()->route('account');
    }
}
