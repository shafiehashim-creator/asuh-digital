<?php

namespace App\Http\Controllers;

use App\Enums\PassStartRule;
use App\Http\Middleware\EnsureCatalogOpen;
use App\Models\Category;
use App\Models\Setting;
use App\Services\CatalogStats;
use App\Services\PassOffer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __invoke(Request $request, PassOffer $offer, CatalogStats $stats): View
    {
        // Book shelves appear only once the catalogue is open (staff always see them).
        $catalogOpen = EnsureCatalogOpen::isOpenFor($request->user());

        return view('landing', [
            'headline' => Setting::get('landing_headline'),
            'subheadline' => Setting::get('landing_subheadline'),
            'faq' => Setting::get('landing_faq'),
            'refundPolicy' => Setting::get('refund_policy'),
            'pilotDays' => (int) Setting::get('pilot_duration_days'),
            'passStartRule' => PassStartRule::tryFrom(Setting::get('pass_start_rule')) ?? PassStartRule::OnPayment,
            'offer' => $offer,
            'catalogOpen' => $catalogOpen,
            'stats' => $stats->summary(),
            'popular' => $catalogOpen ? $stats->popular() : null,
            'newest' => $catalogOpen ? $stats->newest() : collect(),
            'categories' => $catalogOpen
                ? Category::query()->whereHas('books', fn ($q) => $q->published())->orderBy('sort_order')->get()
                : Category::query()->orderBy('sort_order')->get(),
        ]);
    }
}
