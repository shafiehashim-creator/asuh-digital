<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\CatalogStats;
use App\Services\PassOffer;
use App\Services\PlusOffer;
use App\Services\VideoShowcase;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sales page for Langganan Plus (Phase 2): why e-books and animation go
 * together, what Plus adds over the yearly subscription, a few of the
 * videos, and how upgrading works, before the checkout form. While Plus
 * is shown but not sold, it takes "Beritahu saya" sign-ups instead and
 * points families to the yearly subscription.
 */
class PlusPageController extends Controller
{
    public function __invoke(Request $request, PlusOffer $offer, PassOffer $passOffer, VideoShowcase $showcase, CatalogStats $stats): View|RedirectResponse
    {
        // No separate Plus: the yearly subscription includes the videos.
        if ($offer->merged()) {
            return redirect()->route('pass.show');
        }

        $user = $request->user();
        abort_unless($showcase->isOpenFor($user) && $offer->plan(), 404);

        $plan = $offer->plan();
        $yearly = $passOffer->regularPlan();
        $videos = $showcase->videos();
        $upgrade = $user ? $offer->upgradablePass($user) : null;
        $upgradePrice = $upgrade ? $offer->upgradePriceSen($upgrade) : null;

        return view('plus.show', [
            'plan' => $plan,
            'yearly' => $yearly,
            'price' => $offer->priceSen(),
            'compareAt' => $offer->compareAtSen(),
            'extraSen' => $yearly && $offer->priceSen() > $yearly->price_sen ? $offer->priceSen() - $yearly->price_sen : null,
            'videos' => $videos->take(6),
            'videoCount' => $videos->count(),
            'bookCount' => $stats->summary()['books'],
            'showcase' => $showcase,
            'onSale' => $offer->onSale(),
            'opensOn' => $offer->opensOn(),
            'cta' => match (true) {
                (bool) $user?->canWatchVideos() => ['label' => __('Tonton dalam Mod Anak'), 'url' => route('account.children')],
                ! $offer->onSale() => ['label' => __('Beritahu saya bila dibuka'), 'url' => '#beritahu'],
                $upgradePrice !== null => ['label' => __('Naik taraf · :price', ['price' => Money::short($upgradePrice)]), 'url' => route('purchase.plus')],
                default => ['label' => __('Langgan Plus sekarang'), 'url' => route('purchase.plus')],
            },
            'subscribed' => (bool) $user?->canWatchVideos(),
            'upgradePrice' => $offer->onSale() ? $upgradePrice : null,
            'refundPolicy' => Setting::localized('refund_policy'),
        ]);
    }
}
