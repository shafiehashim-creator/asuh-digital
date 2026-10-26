<?php

namespace App\Mail;

use App\Enums\BillingPeriod;
use App\Models\Pass;
use App\Services\SubscriptionPlans;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A monthly subscription ends in a few days: renew for a month, or move to yearly and save. */
class RenewalReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Pass $pass) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Langganan bulanan anda tamat tidak lama lagi').' · ASUH Digital');
    }

    public function content(): Content
    {
        $plans = app(SubscriptionPlans::class);
        $plus = $this->pass->isPlus();
        $yearly = $plus ? $plans->plus(BillingPeriod::Yearly) : $plans->pass(BillingPeriod::Yearly);
        $route = $plus ? 'purchase.plus' : 'purchase.pass';

        return new Content(
            markdown: 'mail.renewal-reminder',
            with: [
                'pass' => $this->pass,
                'yearly' => $yearly,
                'saving' => $yearly ? SubscriptionPlans::yearlySavingPercent($yearly, $this->pass->plan) : 0,
                'yearlyUrl' => route($route, ['tempoh' => BillingPeriod::Yearly->value]),
                'monthlyUrl' => route($route, ['tempoh' => BillingPeriod::Monthly->value]),
            ],
        );
    }
}
