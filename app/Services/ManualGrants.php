<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Book;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * An admin gives a customer a subscription or a single e-book by hand. Each
 * one is recorded as an order (method "manual", who gave it and why) and
 * goes through PaymentProcessor::markPaid() like any payment, so dates,
 * renewals and receipts behave the same.
 */
class ManualGrants
{
    public function __construct(private PaymentProcessor $payments) {}

    public function plan(User $customer, Plan $plan, int $amountSen, string $note, User $admin, bool $sendReceipt): Order
    {
        if (! $plan->is_active) {
            throw new InvalidArgumentException('Pelan ini tidak aktif.');
        }

        return $this->grant($customer, ['plan_id' => $plan->id], $amountSen, $note, $admin, $sendReceipt);
    }

    public function book(User $customer, Book $book, int $amountSen, string $note, User $admin, bool $sendReceipt): Order
    {
        if ($customer->entitlements()->where('book_id', $book->id)->exists()) {
            throw new InvalidArgumentException('Pelanggan ini sudah memiliki buku ini.');
        }

        return $this->grant($customer, ['book_id' => $book->id], $amountSen, $note, $admin, $sendReceipt);
    }

    /** @param array{plan_id?: int, book_id?: int} $item */
    private function grant(User $customer, array $item, int $amountSen, string $note, User $admin, bool $sendReceipt): Order
    {
        if (! $admin->isAdmin()) {
            throw new InvalidArgumentException('Hanya admin boleh menambah langganan atau buku secara manual.');
        }

        return DB::transaction(function () use ($customer, $item, $amountSen, $note, $admin, $sendReceipt) {
            $order = Order::query()->create($item + [
                'user_id' => $customer->id,
                'amount_sen' => max(0, $amountSen),
                'payment_method' => PaymentMethod::Manual,
                'status' => OrderStatus::Pending,
                'admin_note' => $note,
            ]);

            $this->payments->markPaid($order, $admin, sendReceipt: $sendReceipt);

            return $order->refresh();
        });
    }
}
