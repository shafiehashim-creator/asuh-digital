<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PilotStatus;
use App\Enums\PreorderStatus;
use App\Models\Order;
use App\Models\Pass;
use App\Models\PilotRequest;
use App\Models\Preorder;
use Illuminate\Support\Carbon;

/**
 * Numbers for the admin dashboard, kept out of the widgets so they can be tested.
 */
class DashboardStats
{
    public function __construct(private PreorderSlots $slots) {}

    /**
     * @return array{limit: int, paid: int, awaiting: int, reserved: int, remaining: int}
     */
    public function preorders(): array
    {
        $count = fn (PreorderStatus $status) => Preorder::query()->where('status', $status)->count();

        return [
            'limit' => $this->slots->limit(),
            'paid' => $count(PreorderStatus::Paid),
            'awaiting' => $count(PreorderStatus::AwaitingConfirmation),
            'reserved' => Preorder::query()->where('status', PreorderStatus::Reserved)->where('reserved_until', '>', now())->count(),
            'remaining' => $this->slots->remaining(),
        ];
    }

    /**
     * Paid sales in sen since the given moment (Malaysian week starts Monday).
     */
    public function salesSince(Carbon $from): int
    {
        return (int) Order::query()
            ->where('status', OrderStatus::Paid)
            ->where('paid_at', '>=', $from)
            ->sum('amount_sen');
    }

    /**
     * @return array{today: int, week: int, month: int}
     */
    public function sales(): array
    {
        return [
            'today' => $this->salesSince(now()->startOfDay()),
            'week' => $this->salesSince(now()->startOfWeek(Carbon::MONDAY)),
            'month' => $this->salesSince(now()->startOfMonth()),
        ];
    }

    /**
     * @return array{active: int, expiring: int, waiting: int}
     */
    public function passes(): array
    {
        return [
            'active' => Pass::query()->active()->count(),
            'expiring' => Pass::query()->active()->where('ends_at', '<=', now()->addDays(30))->count(),
            // Paid, but waiting for the launch date to be set.
            'waiting' => Pass::query()->whereNull('starts_at')->count(),
        ];
    }

    /**
     * @return array{total: int, active: int, paying: int}
     */
    public function pilots(): array
    {
        return [
            'total' => PilotRequest::query()->count(),
            'active' => PilotRequest::query()->where('status', PilotStatus::PilotActive)->count(),
            'paying' => PilotRequest::query()->where('status', PilotStatus::Paying)->count(),
        ];
    }
}
