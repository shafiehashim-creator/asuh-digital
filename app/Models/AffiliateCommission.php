<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_sen' => 'integer',
            'percent' => 'float',
            'status' => CommissionStatus::class,
            'payable_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** Past the refund window and not paid yet. */
    public function scopeDue(Builder $query): void
    {
        $query->where('status', CommissionStatus::Pending)->where('payable_at', '<=', now());
    }

    public function isDue(): bool
    {
        return $this->status === CommissionStatus::Pending && $this->payable_at->isPast();
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
