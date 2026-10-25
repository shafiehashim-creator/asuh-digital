<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\ChildMode;
use App\Services\VideoWatching;
use App\Support\VideoEmbed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Watching an animated video in Mod Anak (Phase 2). The provider's video id
 * reaches the browser only here, after the access and lock checks.
 */
class ChildVideoController extends Controller
{
    public function __construct(private ChildMode $childMode, private VideoWatching $watching) {}

    public function show(Request $request, Video $video): View|RedirectResponse
    {
        $child = $this->childMode->child($request->user());
        abort_unless($child && $this->watching->hasAccess($child), 404);
        abort_unless($video->isPublished() && $this->ageMatches($child, $video), 404);

        if ($reason = $this->watching->lockReason($child, $video)) {
            return redirect()->route('child-mode.home')->with('video_locked', $reason);
        }

        return view('child-mode.video', [
            'child' => $child,
            'video' => $video,
            'embed' => VideoEmbed::for($video),
            'progressUrl' => route('child-mode.video.progress', $video),
            'startAt' => $this->watching->lastPosition($child, $video),
            'secondsLeft' => $this->watching->secondsLeftToday($child),
        ]);
    }

    public function progress(Request $request, Video $video): JsonResponse
    {
        $child = $this->childMode->child($request->user());
        abort_unless($child && $this->watching->hasAccess($child) && $video->isPublished() && $this->ageMatches($child, $video), 403);

        $data = $request->validate([
            'seconds' => ['required', 'integer', 'min:0', 'max:600'],
            'position' => ['required', 'integer', 'min:0', 'max:86400'],
            'ended' => ['sometimes', 'boolean'],
        ]);

        $this->watching->record($child, $video, (int) $data['seconds'], (int) $data['position'], (bool) ($data['ended'] ?? false));

        return response()->json(['seconds_left' => $this->watching->secondsLeftToday($child)]);
    }

    private function ageMatches($child, Video $video): bool
    {
        return Video::query()->whereKey($video->id)->forAge($child->age_group)->exists();
    }
}
