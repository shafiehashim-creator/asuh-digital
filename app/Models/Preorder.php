<?php

namespace App\Models;

use App\Enums\AgeGroup;
use App\Enums\PreorderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'order_id', 'name', 'email', 'phone', 'state', 'children_count',
    'children_age_group', 'pdpa_consented_at', 'status', 'reserved_until',
])]
class Preorder extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'children_count' => 'integer',
            'children_age_group' => AgeGroup::class,
            'status' => PreorderStatus::class,
            'pdpa_consented_at' => 'datetime',
            'reserved_until' => 'datetime',
        ];
    }

    /**
     * Pre-orders currently occupying a slot: paid, awaiting bank transfer
     * confirmation, or reserved and not yet expired.
     */
    public function scopeHoldingSlot(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->whereIn('status', [PreorderStatus::Paid, PreorderStatus::AwaitingConfirmation])
                ->orWhere(function (Builder $query) {
                    $query->where('status', PreorderStatus::Reserved)
                        ->where('reserved_until', '>', now());
                });
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
