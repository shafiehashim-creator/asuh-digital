<?php

namespace App\Http\Controllers;

use App\Enums\PassStartRule;
use App\Http\Middleware\EnsureCatalogOpen;
use App\Models\Category;
use App\Models\Setting;
use App\Services\CatalogStats;
use App\Services\PassOffer;
use App\Services\PlusOffer;
use App\Services\VideoShowcase;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __invoke(Request $request, PassOffer $offer, CatalogStats $stats, PlusOffer $plus, VideoShowcase $showcase): View
    {
        // The animated videos come with the yearly subscription (no separate Plus, 2026-10-27).
        $videos = $plus->merged() && $plus->videosPublic() ? $showcase->videos() : collect();

        // Book shelves appear only once the catalogue is open (staff always see them).
        $catalogOpen = EnsureCatalogOpen::isOpenFor($request->user());

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
            'popular' => $catalogOpen ? $stats->popular() : null,
            'newest' => $catalogOpen ? $stats->newest() : collect(),
            'categories' => $catalogOpen
                ? Category::query()->whereHas('books', fn ($q) => $q->published())->orderBy('sort_order')->get()
                : Category::query()->orderBy('sort_order')->get(),
        ]);
    }
}
