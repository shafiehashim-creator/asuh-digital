<?php

namespace App\Http\Controllers;

use App\Enums\PassStartRule;
use App\Http\Middleware\EnsureCatalogOpen;
use App\Models\Setting;
use App\Services\CatalogStats;
use App\Services\PassOffer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sales page for the yearly pass: what buying every book one by one would
 * cost against one payment, what the pass includes, and how it works.
 */
class PassPageController extends Controller
{
    public function __invoke(Request $request, PassOffer $offer, CatalogStats $stats): View
    {
        $catalogOpen = EnsureCatalogOpen::isOpenFor($request->user());

        return view('pass.show', [
            'offer' => $offer,
            'stats' => $stats->summary(),
            'books' => $catalogOpen ? $stats->newest(12) : collect(),
            'catalogOpen' => $catalogOpen,
            'faq' => Setting::localized('landing_faq'),
            'refundPolicy' => Setting::localized('refund_policy'),
            'passStartRule' => PassStartRule::tryFrom(Setting::get('pass_start_rule')) ?? PassStartRule::OnPayment,
        ]);
    }
}
