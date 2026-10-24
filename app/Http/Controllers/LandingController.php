<?php

namespace App\Http\Controllers;

use App\Enums\PassStartRule;
use App\Models\Plan;
use App\Models\Setting;
use App\Services\PreorderSlots;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __invoke(PreorderSlots $slots): View
    {
        return view('landing', [
            'headline' => Setting::get('landing_headline'),
            'subheadline' => Setting::get('landing_subheadline'),
            'faq' => Setting::get('landing_faq'),
            'refundPolicy' => Setting::get('refund_policy'),
            'pilotDays' => (int) Setting::get('pilot_duration_days'),
            'passStartRule' => PassStartRule::tryFrom(Setting::get('pass_start_rule')) ?? PassStartRule::OnPayment,
            'plan' => $slots->plan(),
            'regularPlan' => Plan::findByCode(Plan::PAS_TAHUNAN),
            'slotLimit' => $slots->limit(),
            'slotsRemaining' => $slots->remaining(),
            'soldOut' => $slots->isSoldOut(),
        ]);
    }
}
