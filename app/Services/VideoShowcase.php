<?php

namespace App\Services;

use App\Enums\VideoProvider;
use App\Models\Book;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Support\VideoEmbed;
use App\Support\VideoId;
use Illuminate\Database\Eloquent\Collection;

/**
 * The animated videos on the public pages (Phase 2), to win families over to
 * Langganan Plus: a few free sample videos play in full, every other video
 * shows only its thumbnail and stays locked (its provider id never reaches
 * the browser), and an optional trailer montage tops the video page.
 */
class VideoShowcase
{
    public function __construct(private PlusOffer $offer) {}

    /** Shown once the admin opens the videos and prices Plus; staff always see it. */
    public function isOpenFor(?User $user): bool
    {
        return $this->offer->available() || ($user?->isStaff() ?? false);
    }

    /** @return Collection<int, Video> */
    public function videos(): Collection
    {
        return Video::query()->published()->with('book')
            ->orderByDesc('is_free_sample')->latest('published_at')->get();
    }

    /** @return Collection<int, Video> */
    public function forBook(Book $book): Collection
    {
        return Video::query()->published()->where('book_id', $book->id)
            ->orderByDesc('is_free_sample')->latest('published_at')->get();
    }

    public function trailerId(): ?string
    {
        $id = VideoId::normalise(VideoProvider::YouTube, (string) Setting::get('plus_trailer_url'));

        return VideoId::isValid(VideoProvider::YouTube, $id) ? $id : null;
    }

    /** What the player needs, for free sample videos only. */
    public function embed(Video $video): ?array
    {
        return $video->is_free_sample ? VideoEmbed::for($video) : null;
    }

    /**
     * The next step for this visitor: watch in Mod Anak with Plus, upgrade a
     * Langganan Tahunan, or subscribe.
     *
     * @return array{label: string, url: string}
     */
    public function cta(?User $user): array
    {
        if ($user?->canWatchVideos()) {
            return ['label' => __('Tonton dalam Mod Anak'), 'url' => route('account.children')];
        }

        return [
            'label' => $user && $this->offer->upgradablePass($user) ? __('Naik taraf ke Langganan Plus') : __('Langgan Plus sekarang'),
            'url' => route('purchase.plus'),
        ];
    }
}
