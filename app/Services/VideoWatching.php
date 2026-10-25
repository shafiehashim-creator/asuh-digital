<?php

namespace App\Services;

use App\Models\Child;
use App\Models\ReadingSession;
use App\Models\Setting;
use App\Models\Video;
use App\Models\VideoView;
use Illuminate\Database\Eloquent\Collection;

/**
 * Animated videos in Mod Anak (Phase 2): who may watch, what is locked and
 * why, and the seconds watched. Like reading, time is counted from the
 * player's heartbeat, a little at a time, so leaving a video open or calling
 * the endpoint in a loop does not pile up time.
 */
class VideoWatching
{
    /** Heartbeats carry at most this many seconds; the player sends one every 30. */
    public const MAX_TICK_SECONDS = 35;

    /** Choices for the parent's daily limit, in minutes (null: no limit). */
    public const LIMIT_CHOICES = [15, 30, 45, 60, 90];

    public function isOpen(): bool
    {
        return (bool) Setting::get('videos_open');
    }

    /** Langganan Plus for a parent's child, a licence with videos for a pupil. */
    public function hasAccess(Child $child): bool
    {
        if (! $this->isOpen() && ! ($child->user?->isStaff() ?? false)) {
            return false;
        }

        return $child->isPupil()
            ? (bool) $child->classroom?->institution?->canWatchVideos()
            : (bool) $child->user?->canWatchVideos();
    }

    /** @return Collection<int, Video> */
    public function shelf(Child $child): Collection
    {
        return Video::query()->published()->forAge($child->age_group)->with('book')->latest('published_at')->get();
    }

    /**
     * Why this video cannot be played now, as shown to the child, or null.
     */
    public function lockReason(Child $child, Video $video): ?string
    {
        if ($this->secondsLeftToday($child) === 0) {
            return 'Masa menonton hari ini sudah habis. Jom baca buku pula!';
        }

        if ($child->read_before_watch && $video->book_id
            && ! ReadingSession::query()->where('child_id', $child->id)->where('book_id', $video->book_id)->exists()) {
            return 'Baca buku "'.$video->book?->title.'" dahulu untuk membuka video ini.';
        }

        return null;
    }

    /** Seconds still allowed today, or null when the parent set no limit. */
    public function secondsLeftToday(Child $child): ?int
    {
        if (! $child->daily_video_minutes) {
            return null;
        }

        $watched = (int) VideoView::query()->where('child_id', $child->id)->whereDate('watched_on', today())->sum('seconds');

        return max(0, $child->daily_video_minutes * 60 - $watched);
    }

    public function record(Child $child, Video $video, int $seconds, int $position, bool $ended): VideoView
    {
        $view = VideoView::query()->firstOrCreate(
            ['child_id' => $child->id, 'video_id' => $video->id, 'watched_on' => today()],
        );

        // Never more than the time really passed since the last heartbeat.
        $elapsed = $view->last_tick_at ? (int) $view->last_tick_at->diffInSeconds(now()) : self::MAX_TICK_SECONDS;
        $seconds = max(0, min($seconds, self::MAX_TICK_SECONDS, $elapsed));

        $left = $this->secondsLeftToday($child);
        if ($left !== null) {
            $seconds = min($seconds, $left);
        }

        $view->seconds += $seconds;
        $view->position = max(0, $position);
        $view->completed = $view->completed || $ended;
        $view->last_tick_at = now();
        $view->save();

        return $view;
    }

    /** Where the child stopped in this video, on any day. */
    public function lastPosition(Child $child, Video $video): int
    {
        $view = VideoView::query()->where('child_id', $child->id)->where('video_id', $video->id)->latest('updated_at')->first();

        return $view && ! $view->completed ? $view->position : 0;
    }
}
