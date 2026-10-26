<?php

namespace App\Models;

use App\Enums\ChipMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Services\PromoCodes;
use App\Services\VisitTracker;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'reference', 'user_id', 'plan_id', 'book_id', 'amount_sen', 'currency',
    'payment_method', 'status', 'chip_purchase_id', 'bank_receipt_path',
    'confirmed_by', 'confirmed_at', 'paid_at', 'upgrades_pass_id', 'admin_note', 'referral_institution_id', 'discount_sen', 'source', 'medium', 'campaign',
    'processing_fee_sen', 'chip_method', 'coupon_id', 'affiliate_id',
])]
class Order extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_sen' => 'integer',
            'processing_fee_sen' => 'integer',
            'payment_method' => PaymentMethod::class,
            'chip_method' => ChipMethod::class,
            'status' => OrderStatus::class,
            'confirmed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->reference ??= static::generateReference();

            // Sales channel report: where the buyer's visit first came from.
            if ($order->source === null && $order->payment_method !== PaymentMethod::Manual) {
                $order->fill(app(VisitTracker::class)->attribution());
            }

            // Affiliate whose ?ref= link brought the buyer (a typed affiliate code is set already).
            if ($order->affiliate_id === null && $order->payment_method !== PaymentMethod::Manual) {
                $order->affiliate_id = app(PromoCodes::class)->referredAffiliateId($order->user_id);
            }
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

    /**
     * What the buyer pays: the price (amount_sen, after any school discount)
     * plus the CHIP processing fee for the chosen method. Sales reports count
     * amount_sen only, since the fee goes to CHIP.
     */
    public function totalSen(): int
    {
        return $this->amount_sen + (int) $this->processing_fee_sen;
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    /**
     * A bank transfer whose receipt is in, waiting for an admin to confirm.
     */
    public function isAwaitingConfirmation(): bool
    {
        return $this->status === OrderStatus::Pending
            && $this->payment_method === PaymentMethod::BankTransfer
            && filled($this->bank_receipt_path);
    }

    /**
     * What was bought, for pay pages, receipts and CHIP.
     */
    public function itemName(): string
    {
        if ($this->book_id) {
            return 'E-book: '.($this->book?->title ?? 'ASUH Digital');
        }

        if ($this->upgrades_pass_id) {
            return 'Naik taraf ke '.($this->plan?->name ?? 'Langganan Plus');
        }

        return $this->plan?->name ?? 'ASUH Digital';
    }

    /** The school whose parent code gave this order its discount. */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /** "Diskaun SEKOLAH" / "Diskaun RAYA20", for pay pages and receipts. */
    public function discountLabel(): string
    {
        return match (true) {
            $this->coupon_id !== null => 'Diskaun kod '.$this->coupon?->code,
            $this->referral_institution_id !== null => 'Diskaun '.($this->referralInstitution?->name ?? 'sekolah'),
            default => 'Diskaun',
        };
    }

    public function referralInstitution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'referral_institution_id');
    }

    /** The subscription this order upgrades to Langganan Plus (Phase 2). */
    public function upgradesPass(): BelongsTo
    {
        return $this->belongsTo(Pass::class, 'upgrades_pass_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
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
