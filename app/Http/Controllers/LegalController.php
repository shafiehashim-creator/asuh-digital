<?php

namespace App\Http\Controllers;

use App\Enums\PassStartRule;
use App\Models\Setting;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function privacy(): View
    {
        return view($this->localized('legal.privacy'), $this->company());
    }

    public function terms(): View
    {
        return view($this->localized('legal.terms'), $this->company() + [
            'refundPolicy' => Setting::localized('refund_policy'),
            'passStartRule' => PassStartRule::tryFrom(Setting::get('pass_start_rule')) ?? PassStartRule::OnPayment,
            'slotLimit' => (int) Setting::get('preorder_slot_limit'),
        ]);
    }

    public function about(): View
    {
        return view('about', $this->company() + [
            'whatsapp' => Setting::get('whatsapp_number'),
        ]);
    }

    public function refund(): View
    {
        return view($this->localized('legal.refund'), $this->company() + [
            'refundPolicy' => Setting::localized('refund_policy'),
        ]);
    }

    /** Digital products only; payment gateways ask merchants for a delivery policy. */
    public function delivery(): View
    {
        return view($this->localized('legal.delivery'), $this->company() + [
            'startsOnLaunch' => PassStartRule::tryFrom(Setting::get('pass_start_rule')) === PassStartRule::OnLaunch,
        ]);
    }

    /** Legal texts have a full English version (legal/en/*), kept apart for review. */
    private function localized(string $view): string
    {
        $english = str_replace('legal.', 'legal.en.', $view);

        return app()->getLocale() === 'en' && view()->exists($english) ? $english : $view;
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
