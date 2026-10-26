<?php

namespace App\Services;

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Mail\AffiliateApplied;
use App\Mail\AffiliateApproved;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Affiliates (user decision 2026-10-26): account holders apply, an admin
 * approves, and each paid order credited to them earns a percentage of the
 * price (after any discount, without the CHIP fee). A commission is due once
 * the refund window has passed, and the admin pays it by bank transfer.
 */
class Affiliates
{
    public function isOpen(): bool
    {
        return (bool) Setting::get('affiliates_open');
    }

    /** @param  array{promotion_channels: string, bank_name: string, bank_account_name: string, bank_account_number: string}  $data */
    public function apply(User $user, array $data): Affiliate
    {
        $affiliate = $user->affiliate()->first() ?? new Affiliate;
        $affiliate->fill($data);
        $affiliate->user()->associate($user);
        $affiliate->code ??= Affiliate::newCode($user->name);

        // A rejected applicant may apply again; an approved one just updates details.
        if (! $affiliate->exists || $affiliate->status === AffiliateStatus::Rejected) {
            $affiliate->status = AffiliateStatus::Pending;
        }

        $isNewApplication = $affiliate->isDirty('status') && $affiliate->status === AffiliateStatus::Pending;
        $affiliate->save();

        $adminEmails = array_filter((array) Setting::get('admin_notification_emails'));

        if ($isNewApplication && $adminEmails) {
            Mail::to($adminEmails)->send(new AffiliateApplied($affiliate));
        }

        return $affiliate;
    }

    public function approve(Affiliate $affiliate): void
    {
        $firstTime = $affiliate->approved_at === null;
        $affiliate->forceFill(['status' => AffiliateStatus::Approved, 'approved_at' => $affiliate->approved_at ?? now()])->save();

        if ($firstTime) {
            Mail::to($affiliate->user)->send(new AffiliateApproved($affiliate));
        }
    }

    /** Idempotent: an order earns one commission at most. */
    public function recordCommission(Order $order): ?AffiliateCommission
    {
        $affiliate = $order->affiliate;

        if (! $affiliate?->isActive() || $order->amount_sen <= 0 || $affiliate->user_id === $order->user_id) {
            return null;
        }

        $percent = $affiliate->effectivePercent();

        return AffiliateCommission::query()->firstOrCreate(['order_id' => $order->id], [
            'affiliate_id' => $affiliate->id,
            'amount_sen' => (int) round($order->amount_sen * $percent / 100),
            'percent' => $percent,
            'payable_at' => ($order->paid_at ?? now())->copy()->addDays((int) Setting::get('affiliate_hold_days')),
            'status' => CommissionStatus::Pending,
        ]);
    }

    /**
     * Marks every due commission of one affiliate as paid, after the admin
     * has transferred the total.
     *
     * @return int the amount paid in sen
     */
    public function payDue(Affiliate $affiliate, User $admin, ?string $reference): int
    {
        return DB::transaction(function () use ($affiliate, $admin, $reference) {
            $due = $affiliate->commissions()->due()->lockForUpdate()->get();

            AffiliateCommission::query()->whereKey($due->modelKeys())->update([
                'status' => CommissionStatus::Paid,
                'paid_at' => now(),
                'paid_by' => $admin->id,
                'payment_reference' => $reference,
            ]);

            return (int) $due->sum('amount_sen');
        });
    }

    /** @return array{pending: int, due: int, paid: int, orders: int} sen totals */
    public function summary(Affiliate $affiliate): array
    {
        $rows = $affiliate->commissions()->get();
        $open = $rows->where('status', CommissionStatus::Pending);

        return [
            'pending' => (int) $open->filter(fn ($c) => ! $c->isDue())->sum('amount_sen'),
            'due' => (int) $open->filter(fn ($c) => $c->isDue())->sum('amount_sen'),
            'paid' => (int) $rows->where('status', CommissionStatus::Paid)->sum('amount_sen'),
            'orders' => $rows->where('status', '!=', CommissionStatus::Cancelled)->count(),
        ];
    }
}
