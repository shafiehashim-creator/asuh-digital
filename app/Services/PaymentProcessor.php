<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PassStartRule;
use App\Enums\PreorderStatus;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single place where an order becomes paid and its pass is created.
 * Used by the CHIP callback and by the admin bank transfer confirmation.
 *
 * Idempotent: CHIP may deliver the same callback several times, so a second
 * call for an order that is already paid changes nothing.
 */
class PaymentProcessor
{
    /**
     * @return bool true if this call marked the order as paid
     */
    public function markPaid(Order $order, ?User $confirmedBy = null): bool
    {
        $newlyPaid = DB::transaction(function () use ($order, $confirmedBy) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status === OrderStatus::Paid) {
                return false;
            }

            $locked->update([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
                'confirmed_by' => $confirmedBy?->id,
                'confirmed_at' => $confirmedBy ? now() : null,
            ]);

            // A payment that lands after the reservation expired is still
            // honoured (agreed with the business), even if that briefly puts
            // the slot count above the limit. The admin can spot these because
            // paid_at is later than reserved_until.
            $locked->preorder?->update(['status' => PreorderStatus::Paid]);

            if ($locked->plan) {
                [$startsAt, $endsAt] = $this->passPeriod($locked->plan->duration_months ?? 12);

                Pass::query()->firstOrCreate(
                    ['order_id' => $locked->id],
                    [
                        'user_id' => $locked->user_id,
                        'plan_id' => $locked->plan_id,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                    ],
                );
            }

            return true;
        });

        if ($newlyPaid) {
            OrderPaid::dispatch($order->fresh());
        }

        return $newlyPaid;
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public function passPeriod(int $months): array
    {
        $rule = PassStartRule::tryFrom(Setting::get('pass_start_rule')) ?? PassStartRule::OnPayment;

        if ($rule === PassStartRule::OnLaunch) {
            $launch = Setting::get('launch_date');

            if (! $launch) {
                // Start date is filled in once the admin sets the launch date.
                return [null, null];
            }

            $start = Carbon::parse($launch)->startOfDay();
            $start = $start->isPast() ? now() : $start;
        } else {
            $start = now();
        }

        return [$start, $start->copy()->addMonthsNoOverflow($months)];
    }
}
