<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PreorderStatus;
use App\Models\Preorder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('asuh:expire-reservations')]
#[Description('Release pre-order slots whose payment window has passed')]
class ExpireReservations extends Command
{
    /**
     * Slot counting already ignores lapsed reservations; this tidies up the
     * records so the admin panel and the buyer see the right status.
     */
    public function handle(): int
    {
        $expired = 0;

        Preorder::query()
            ->where('status', PreorderStatus::Reserved)
            ->where('reserved_until', '<=', now())
            ->pluck('id')
            ->each(function (int $id) use (&$expired) {
                DB::transaction(function () use ($id, &$expired) {
                    // Re-read under a row lock in case a payment landed meanwhile.
                    $preorder = Preorder::query()->lockForUpdate()->find($id);

                    if ($preorder?->status !== PreorderStatus::Reserved || $preorder->reserved_until->isFuture()) {
                        return;
                    }

                    $preorder->update(['status' => PreorderStatus::Expired]);
                    $preorder->order()
                        ->where('status', OrderStatus::Pending)
                        ->update(['status' => OrderStatus::Failed]);

                    $expired++;
                });
            });

        $this->info("{$expired} tempahan telah dilepaskan.");

        return self::SUCCESS;
    }
}
