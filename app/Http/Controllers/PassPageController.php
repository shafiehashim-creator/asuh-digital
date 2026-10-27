<?php

namespace App\Http\Controllers;

use App\Enums\AgeGroup;
use App\Enums\BillingPeriod;
use App\Enums\PassStartRule;
use App\Http\Middleware\EnsureCatalogOpen;
use App\Models\Book;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\Video;
use App\Services\CatalogStats;
use App\Services\PassOffer;
use App\Services\PlusOffer;
use App\Services\SubscriptionPlans;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Sales page for the yearly subscription, laid out as one long sales letter
 * (user decision 2026-10-27, after the Durioo+ page): the price first, the
 * parents' worries, why ASUH is different, the books by age, a comparison,
 * the plans, how to start, who it is for, questions and a last call.
 */
class PassPageController extends Controller
{
    public function __invoke(Request $request, PassOffer $offer, CatalogStats $stats, SubscriptionPlans $plans, PlusOffer $plus): View
    {
        $catalogOpen = EnsureCatalogOpen::isOpenFor($request->user());
        $price = $offer->priceSen();
        $compareAt = $offer->compareAtSen();
        // Monthly is sold once the early bird is over (as on the checkout).
        $monthly = $offer->available() && ! $offer->earlyBird() ? $plans->pass(BillingPeriod::Monthly) : null;
        $yearly = $offer->plan();

        // What a year costs the other way, against what it costs with us.
        $yearAgainst = match (true) {
            $monthly !== null => ['label' => __('Bayar bulanan, 12 bulan'), 'sen' => $monthly->price_sen * 12],
            $compareAt !== null => ['label' => __('Harga biasa'), 'sen' => $compareAt],
            default => null,
        };
        $yearAgainst = $yearAgainst && $price && $yearAgainst['sen'] > $price ? $yearAgainst : null;

        $testimonials = Testimonial::query()->published()->get();
        // The first quote sits under the price; the section below shows the others.
        $spotlight = $testimonials->first(fn (Testimonial $t) => filled($t->localQuote()));

        return view('pass.show', [
            'offer' => $offer,
            'price' => $price,
            'compareAt' => $compareAt,
            'monthly' => $monthly,
            'yearAgainst' => $yearAgainst,
            'savingPercent' => $yearAgainst ? (int) round(100 - $price / $yearAgainst['sen'] * 100) : null,
            'monthlySavingPercent' => $monthly && $yearly ? SubscriptionPlans::yearlySavingPercent($yearly, $monthly) : null,
            'stats' => $stats->summary(),
            // The animated videos included in the subscription (no separate Plus).
            'videoCount' => $plus->merged() && $plus->videosPublic() ? Video::query()->published()->count() : 0,
            'shelves' => $catalogOpen ? [
                AgeGroup::SixToNine->getLabel() => $this->shelf(AgeGroup::SixToNine),
                AgeGroup::TenToThirteen->getLabel() => $this->shelf(AgeGroup::TenToThirteen),
            ] : [],
            'catalogOpen' => $catalogOpen,
            'testimonials' => $testimonials->count() >= Testimonial::MIN_SHOWN ? $testimonials->reject(fn (Testimonial $t) => $t->is($spotlight))->values() : collect(),
            'spotlight' => $spotlight,
            'faq' => Setting::localized('landing_faq'),
            'refundPolicy' => Setting::localized('refund_policy'),
            'passStartRule' => PassStartRule::tryFrom(Setting::get('pass_start_rule')) ?? PassStartRule::OnPayment,
        ]);
    }

    /** @return Collection<int, Book> the newest books for one age group */
    private function shelf(AgeGroup $age)
    {
        return Book::query()->published()->forAge($age)->latest('published_at')->limit(14)->get();
    }
}
