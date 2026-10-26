<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\Affiliates;

class RecordAffiliateCommission
{
    public function __construct(private Affiliates $affiliates) {}

    public function handle(OrderPaid $event): void
    {
        $this->affiliates->recordCommission($event->order);
    }
}
