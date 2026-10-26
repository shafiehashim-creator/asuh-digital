<?php

namespace App\Services;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PromoProduct;
use App\Models\Book;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Regular sales once the early bird is over: the yearly pass at its normal
 * price (no slot limit, no reservation) and single e-books. Payment, admin
 * confirmation and receipts reuse the existing CHIP/bank transfer flow.
 */
class Purchases
{
    public function __construct(
        private PreorderSlots $slots,
        private CreateNewUser $createNewUser,
        private PlusOffer $plusOffer,
        private PromoCodes $promoCodes,
    ) {}

    /**
     * Early bird first: the regular pass is sold only once its slots are gone.
     */
    public function earlyBirdOpen(): bool
    {
        $plan = $this->slots->plan();

        return $plan?->is_active && ! $this->slots->isSoldOut();
    }

    public function regularPlan(): ?Plan
    {
        $plan = Plan::findByCode(Plan::PAS_TAHUNAN);

        return $plan?->is_active ? $plan : null;
    }

    /**
     * @param  array<string, mixed>  $input  validated input (account fields for guests)
     */
    public function buyPass(?User $user, array $input): Order
    {
        $plan = $this->regularPlan() ?? throw new InvalidArgumentException('Langganan Tahunan tidak dijual buat masa ini.');

        return $this->order($user, $input, ['plan_id' => $plan->id], $plan->price_sen, PromoProduct::Pass);
    }

    /**
     * A new Langganan Plus, or a renewal of one. Families on Langganan Tahunan
     * upgrade instead (upgradeToPlus), so they never pay twice for the same days.
     *
     * @param  array<string, mixed>  $input
     */
    public function buyPlus(?User $user, array $input): Order
    {
        $plan = $this->plusOffer->available() ? $this->plusOffer->plan() : null;

        if (! $plan) {
            throw new InvalidArgumentException('Langganan Plus tidak dijual buat masa ini.');
        }

        if ($user && $this->plusOffer->upgradablePass($user)) {
            throw new InvalidArgumentException('Anda sudah ada Langganan Tahunan. Naik taraf ke Langganan Plus dengan membayar bezanya sahaja.');
        }

        return $this->order($user, $input, ['plan_id' => $plan->id], $plan->price_sen, PromoProduct::Plus);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function upgradeToPlus(User $user, array $input): Order
    {
        $plan = $this->plusOffer->available() ? $this->plusOffer->plan() : null;
        $pass = $this->plusOffer->upgradablePass($user);
        $price = $pass ? $this->plusOffer->upgradePriceSen($pass) : null;

        if (! $plan || ! $pass || ! $price) {
            throw new InvalidArgumentException('Tiada langganan untuk dinaik taraf.');
        }

        return $this->order($user, $input, ['plan_id' => $plan->id, 'upgrades_pass_id' => $pass->id], $price, PromoProduct::Upgrade);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function buyBook(?User $user, Book $book, array $input): Order
    {
        if (! $book->isPublished() || ! $book->single_price_sen) {
            throw new InvalidArgumentException('Buku ini tidak dijual secara single.');
        }

        if ($user?->canReadBook($book)) {
            throw new InvalidArgumentException('Anda sudah boleh membaca buku ini.');
        }

        return $this->order($user, $input, ['book_id' => $book->id], $book->single_price_sen, PromoProduct::Book);
    }

    /**
     * Reuses an unpaid order for the same item, so a second click or a
     * change of payment method never creates duplicate orders.
     *
     * @param  array{plan_id?: int, book_id?: int, upgrades_pass_id?: int}  $item
     */
    private function order(?User $user, array $input, array $item, int $priceSen, PromoProduct $product): Order
    {
        $isNewAccount = $user === null;
        $method = PaymentMethod::from($input['payment_method']);

        // One "Kod promosi": a school code, a coupon or an affiliate code.
        // Old links still send school_code.
        $referral = $this->promoCodes->apply(
            $input['promo_code'] ?? $input['school_code'] ?? null, $product, $user, $priceSen,
        );
        $priceSen -= $referral['discount_sen'];

        $order = DB::transaction(function () use ($user, $input, $item, $priceSen, $method, $referral) {
            $user ??= $this->createNewUser->create($input);

            $existing = $user->orders()
                ->where('status', OrderStatus::Pending)
                ->whereDoesntHave('preorder')
                ->where($item)
                // A new Langganan Plus is a different item from an upgrade to it.
                ->when(! isset($item['upgrades_pass_id']), fn ($query) => $query->whereNull('upgrades_pass_id'))
                ->latest()
                ->first();

            if ($existing && ! $existing->bank_receipt_path) {
                // chip_purchase_id is kept on purpose: if the buyer still
                // completes an earlier CHIP checkout, its callback must find
                // this order.
                // The CHIP method and its fee are chosen again on the pay page.
                // An affiliate credited earlier (from a ?ref= link) stays unless a new code names one.
                $existing->update(['payment_method' => $method, 'amount_sen' => $priceSen, 'processing_fee_sen' => 0, 'chip_method' => null]
                    + array_filter($referral, fn ($value, $key) => $key !== 'affiliate_id' || $value !== null, ARRAY_FILTER_USE_BOTH));

                return $existing;
            }

            return $existing ?? Order::query()->create($item + $referral + [
                'user_id' => $user->id,
                'amount_sen' => $priceSen,
                'payment_method' => $method,
                'status' => OrderStatus::Pending,
            ]);
        });

        if ($isNewAccount) {
            event(new Registered($order->user));
        }

        return $order;
    }
}
