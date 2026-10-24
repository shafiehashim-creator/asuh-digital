<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PreorderStatus;
use App\Models\Order;
use App\Models\PaymentLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Bank transfer flow: the buyer uploads a receipt, an admin confirms or
 * rejects it. Confirmation goes through PaymentProcessor like CHIP does.
 */
class BankTransfers
{
    public const DISK = 'local';

    public function __construct(private PaymentProcessor $payments) {}

    /**
     * A receipt can be (re-)uploaded while the order is unpaid. Like late
     * CHIP payments, a receipt for a lapsed reservation is still accepted:
     * the buyer has already transferred the money, and the admin decides.
     */
    public function canUploadReceipt(Order $order): bool
    {
        return $order->payment_method === PaymentMethod::BankTransfer
            && in_array($order->status, [OrderStatus::Pending, OrderStatus::Failed], true)
            && $order->preorder?->status !== PreorderStatus::Refunded;
    }

    public function uploadReceipt(Order $order, UploadedFile $file, ?string $ip = null): void
    {
        if (! $this->canUploadReceipt($order)) {
            throw new InvalidArgumentException('This order cannot take a receipt.');
        }

        $path = $file->storeAs(
            'receipts',
            $order->reference.'-'.Str::lower(Str::random(8)).'.'.$file->extension(),
            self::DISK,
        );

        DB::transaction(function () use ($order, $path, $ip) {
            $previous = $order->bank_receipt_path;

            $order->update(['bank_receipt_path' => $path, 'status' => OrderStatus::Pending]);
            $order->preorder?->update(['status' => PreorderStatus::AwaitingConfirmation]);

            $this->log($order, 'receipt_uploaded', ['path' => $path], $ip);

            if ($previous && $previous !== $path) {
                Storage::disk(self::DISK)->delete($previous);
            }
        });
    }

    public function confirm(Order $order, User $admin): bool
    {
        $this->ensureAdmin($admin);

        $paid = $this->payments->markPaid($order, $admin);
        $this->log($order, 'confirmed', ['by' => $admin->id]);

        return $paid;
    }

    public function reject(Order $order, User $admin, string $reason): void
    {
        $this->ensureAdmin($admin);

        if ($order->status === OrderStatus::Paid) {
            throw new InvalidArgumentException('A paid order cannot be rejected; refund it instead.');
        }

        DB::transaction(function () use ($order, $admin, $reason) {
            $order->update(['status' => OrderStatus::Failed]);
            // Releases the slot for other buyers.
            $order->preorder?->update(['status' => PreorderStatus::Expired]);

            $this->log($order, 'rejected', ['by' => $admin->id, 'reason' => $reason]);
        });
    }

    private function ensureAdmin(User $user): void
    {
        // Editors must never touch money (CLAUDE.md), only the Super Admin.
        if (! $user->isAdmin()) {
            throw new InvalidArgumentException('Only an admin can confirm or reject payments.');
        }
    }

    private function log(Order $order, string $event, array $payload, ?string $ip = null): void
    {
        PaymentLog::query()->create([
            'provider' => 'bank_transfer',
            'event' => $event,
            'reference' => $order->reference,
            'order_id' => $order->id,
            'payload' => $payload,
            'ip_address' => $ip,
        ]);
    }
}
