<?php

namespace App\Http\Controllers;

use App\Enums\AgeGroup;
use App\Enums\Game;
use App\Enums\PassStartRule;
use App\Http\Middleware\EnsureCatalogOpen;
use App\Models\Book;
use App\Models\Category;
use App\Models\Setting;
use App\Services\Campaigns;
use App\Services\CatalogStats;
use App\Services\GamePlaying;
use App\Services\PassOffer;
use App\Services\PlusOffer;
use App\Services\VideoShowcase;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingController extends Controller
{
    /** Counts are shown only once they impress; below these the hero names the thing without a number. */
    public const MIN_BOOKS_SHOWN = 20;

    public const MIN_VIDEOS_SHOWN = 10;

    public function __invoke(Request $request, PassOffer $offer, CatalogStats $stats, PlusOffer $plus, VideoShowcase $showcase, Campaigns $campaigns, GamePlaying $games): View
    {
        // The animated videos come with the yearly subscription (no separate Plus, 2026-10-27).
        $videos = $plus->merged() && $plus->videosPublic() ? $showcase->videos() : collect();

        // Book shelves appear only once the catalogue is open (staff always see them).
        $catalogOpen = EnsureCatalogOpen::isOpenFor($request->user());

        $popular = $catalogOpen ? $stats->popular() : null;
        $campaign = $campaigns->banner() ?? $campaigns->bar();

        // "How old is your child?": what each age group gets (user decision 2026-10-29).
        $ages = collect([AgeGroup::SixToNine, AgeGroup::TenToThirteen])->mapWithKeys(fn (AgeGroup $age) => [$age->value => [
            'books' => $catalogOpen ? Book::query()->published()->forAge($age)->latest('published_at')->limit(6)->get() : collect(),
            'videos' => $videos->filter(fn ($video) => in_array($video->age_group, [$age, AgeGroup::Both], true))->take(2)->values(),
        ]]);

        return view('landing', [
            'headline' => Setting::localized('landing_headline'),
            'subheadline' => Setting::localized('landing_subheadline'),
            'faq' => Setting::localized('landing_faq'),
            'refundPolicy' => Setting::localized('refund_policy'),
            'pilotDays' => (int) Setting::get('pilot_duration_days'),
            'passStartRule' => PassStartRule::tryFrom(Setting::get('pass_start_rule')) ?? PassStartRule::OnPayment,
            'offer' => $offer,
            'catalogOpen' => $catalogOpen,
            'stats' => $stats->summary(),
            'videoCount' => $videos->count(),
            'videoSample' => $videos->firstWhere('is_free_sample', true),
            'videoRow' => $videos->take(8),
            'showcase' => $showcase,
            'popular' => $popular,
            // The real shelf drawn in the hero's phone.
            'heroBooks' => $popular ? $popular['books']->take(3) : collect(),
            'campaign' => $campaign,
            'campaignPrices' => $campaign ? $campaigns->prices($campaign) : null,
            'gamesOpen' => $games->isOpen(),
            'gameCount' => count(Game::cases()),
            'ages' => $ages,
            'newest' => $catalogOpen ? $stats->newest() : collect(),
            'categories' => $catalogOpen
                ? Category::query()->whereHas('books', fn ($q) => $q->published())->orderBy('sort_order')->get()
                : Category::query()->orderBy('sort_order')->get(),
        ]);
    }
}
