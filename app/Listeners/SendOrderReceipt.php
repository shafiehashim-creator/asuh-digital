<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Mail\OrderReceipt;
use Illuminate\Support\Facades\Mail;

class SendOrderReceipt
{
    public function handle(OrderPaid $event): void
    {
        if (! $event->sendReceipt) {
            return;
        }

        Mail::to($event->order->user)->send(new OrderReceipt($event->order));
    }
}
