<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'reference', 'user_id', 'plan_id', 'book_id', 'amount_sen', 'currency',
    'payment_method', 'status', 'chip_purchase_id', 'bank_receipt_path',
    'confirmed_by', 'confirmed_at', 'paid_at',
])]
class Order extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_sen' => 'integer',
            'payment_method' => PaymentMethod::class,
            'status' => OrderStatus::class,
            'confirmed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->reference ??= static::generateReference();
        });
    }

    /**
     * Human-friendly unique reference sent to CHIP and printed on receipts.
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'ASUH-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function pass(): HasOne
    {
        return $this->hasOne(Pass::class);
    }

    public function preorder(): HasOne
    {
        return $this->hasOne(Preorder::class);
    }
}
