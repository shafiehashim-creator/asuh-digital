<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once, when an order first becomes paid. The receipt e-mail listens to it.
 */
class OrderPaid
{
    use Dispatchable, SerializesModels;

    /** $sendReceipt is false only when an admin adds an order by hand and chooses not to e-mail. */
    public function __construct(public Order $order, public bool $sendReceipt = true) {}
}
