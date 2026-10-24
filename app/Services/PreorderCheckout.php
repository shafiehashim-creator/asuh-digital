<?php

namespace App\Services;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PreorderStatus;
use App\Exceptions\PreorderUnavailable;
use App\Models\Order;
use App\Models\Preorder;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Reserves one of the limited pre-order slots and creates the pending order.
 *
 * Counting the free slots and inserting the reservation happen under one
 * cache lock, so two buyers can never both take the last slot.
 */
class PreorderCheckout
{
    private const LOCK = 'preorder-slots';

    public function __construct(
        private PreorderSlots $slots,
        private CreateNewUser $createNewUser,
    ) {}

    /**
     * The buyer's reservation that still holds a slot, if any. One early
     * bird pass per account keeps the 100 slots fair.
     */
    public function existingFor(User $user): ?Preorder
    {
        return $user->preorders()->holdingSlot()->with('order')->latest()->first();
    }

    /**
     * @param  array<string, mixed>  $input  validated checkout input
     *
     * @throws PreorderUnavailable
     */
    public function reserve(?User $user, array $input): Preorder
    {
        $plan = $this->slots->plan();

        if (! $plan || ! $plan->is_active) {
            throw PreorderUnavailable::closed();
        }

        $method = PaymentMethod::from($input['payment_method']);
        $isNewAccount = $user === null;

        $preorder = Cache::lock(self::LOCK, 10)->block(5, function () use ($user, $input, $plan, $method) {
            if ($this->slots->isSoldOut()) {
                throw PreorderUnavailable::soldOut();
            }

            return DB::transaction(function () use ($user, $input, $plan, $method) {
                $user ??= $this->createNewUser->create($input);

                if (! $user->phone && ! empty($input['phone'])) {
                    $user->forceFill(['phone' => $input['phone']])->save();
                }

                $order = Order::query()->create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'amount_sen' => $plan->price_sen,
                    'payment_method' => $method,
                    'status' => OrderStatus::Pending,
                ]);

                return Preorder::query()->create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $input['phone'] ?? $user->phone,
                    'state' => $input['state'],
                    'children_count' => $input['children_count'],
                    'children_age_group' => $input['children_age_group'],
                    'pdpa_consented_at' => now(),
                    'status' => PreorderStatus::Reserved,
                    'reserved_until' => $this->reservedUntil($method),
                ]);
            });
        });

        // Sent after the lock is released; triggers the verification e-mail.
        if ($isNewAccount) {
            event(new Registered($preorder->user));
        }

        return $preorder;
    }

    public function reservedUntil(PaymentMethod $method): Carbon
    {
        return match ($method) {
            PaymentMethod::Chip => now()->addMinutes((int) Setting::get('preorder_reservation_minutes')),
            PaymentMethod::BankTransfer => now()->addHours((int) Setting::get('bank_transfer_reservation_hours')),
        };
    }
}
