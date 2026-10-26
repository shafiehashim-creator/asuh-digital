<?php

namespace App\Http\Controllers;

use App\Enums\PassStartRule;
use App\Models\Setting;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function privacy(): View
    {
        return view('legal.privacy', $this->company());
    }

    public function terms(): View
    {
        return view('legal.terms', $this->company() + [
            'refundPolicy' => Setting::get('refund_policy'),
            'passStartRule' => PassStartRule::tryFrom(Setting::get('pass_start_rule')) ?? PassStartRule::OnPayment,
            'slotLimit' => (int) Setting::get('preorder_slot_limit'),
        ]);
    }

    public function refund(): View
    {
        return view('legal.refund', $this->company() + [
            'refundPolicy' => Setting::get('refund_policy'),
        ]);
    }

    /** Digital products only; payment gateways ask merchants for a delivery policy. */
    public function delivery(): View
    {
        return view('legal.delivery', $this->company() + [
            'startsOnLaunch' => PassStartRule::tryFrom(Setting::get('pass_start_rule')) === PassStartRule::OnLaunch,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function company(): array
    {
        return [
            // Brand name from CLAUDE.md until the admin fills in the legal entity.
            'companyName' => Setting::get('company_name') ?: 'ASUH, Galeri Ilmu Media Group',
            'companySsm' => Setting::get('company_ssm_no'),
            'companyAddress' => Setting::get('company_address'),
            'contactEmail' => Setting::get('contact_email'),
        ];
    }
}
