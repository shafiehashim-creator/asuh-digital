<?php

namespace App\Services;

use App\Enums\ContributorRole;
use App\Enums\OrderStatus;
use App\Models\Book;
use App\Models\Order;
use App\Models\Pass;
use App\Models\Plan;
use App\Models\ReadingSession;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Monthly royalties (2026-10-26), a pool split by reads (the model chosen
 * with the user):
 *
 * - Subscription revenue is recognised over the subscription's days, so a
 *   RM99 year counts about RM8.25 a month, not RM99 in the month it was paid.
 *   For Langganan Plus only the e-book share counts (the rest is animation).
 * - The pool is a percentage of that revenue, split between books by reads.
 *   A read is one family child reading a book for at least N minutes in the
 *   month, counted once (pupils on school licences are not in the pool).
 * - Single e-book sales earn a percentage of the price: the book's own
 *   rate when its contract differs, else the default in settings.
 * - Each book's royalty goes to its contributors by their share (%).
 */
class Royalties
{
    /**
     * @return array{
     *     month: Carbon, pool_percent: float, revenue_sen: int, pool_sen: int, reads: int,
     *     books: Collection<int, array{book: Book, reads: int, share: float, pool_sen: int, single_sales_sen: int, single_sen: int, single_rate: float, total_sen: int, shares_ok: bool}>,
     *     contributors: Collection<int, array{name: string, role: string, bank: string, books: int, total_sen: int}>,
     * }
     */
    public function forMonth(Carbon $month, ?float $poolPercent = null): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $poolPercent ??= (float) Setting::get('royalty_pool_percent');

        $revenue = $this->recognisedRevenue($start, $end);
        $pool = (int) round($revenue * $poolPercent / 100);
        $reads = $this->reads($start, $end);
        $totalReads = (int) $reads->sum();
        $singles = $this->singleSales($start, $end);
        $singlePercent = (float) Setting::get('royalty_single_percent');

        $books = Book::query()
            ->whereIn('id', $reads->keys()->merge($singles->keys())->unique())
            ->with('contributors')
            ->orderBy('title')
            ->get()
            ->map(function (Book $book) use ($reads, $totalReads, $pool, $singles, $singlePercent) {
                $bookReads = (int) ($reads[$book->id] ?? 0);
                $share = $totalReads ? $bookReads / $totalReads : 0;
                $fromPool = (int) round($pool * $share);
                $singleSales = (int) ($singles[$book->id] ?? 0);
                $rate = $book->royalty_single_percent ?? $singlePercent;
                $fromSingles = (int) round($singleSales * $rate / 100);

                return [
                    'book' => $book,
                    'reads' => $bookReads,
                    'share' => $share * 100,
                    'pool_sen' => $fromPool,
                    'single_sales_sen' => $singleSales,
                    'single_sen' => $fromSingles,
                    'single_rate' => $rate,
                    'total_sen' => $fromPool + $fromSingles,
                    // Shares should add up to 100%, or part of the royalty has no payee.
                    'shares_ok' => abs($book->contributors->sum('pivot.share_percent') - 100) < 0.01,
                ];
            })
            ->sortByDesc('total_sen')
            ->values();

        return [
            'month' => $start,
            'pool_percent' => $poolPercent,
            'revenue_sen' => $revenue,
            'pool_sen' => $pool,
            'reads' => $totalReads,
            'books' => $books,
            'contributors' => $this->byContributor($books),
        ];
    }

    /**
     * Subscription money that belongs to this month: each paid subscription
     * order spread evenly over the days its pass (or upgrade) covers.
     */
    public function recognisedRevenue(Carbon $start, Carbon $end): int
    {
        $plusShare = (float) Setting::get('royalty_plus_ebook_share') / 100;
        $plus = Plan::query()->whereIn('code', Plan::PLUS_CODES)->pluck('id')->all();

        $orders = Order::query()
            ->where('status', OrderStatus::Paid)
            ->whereNotNull('plan_id')
            ->where('amount_sen', '>', 0)
            ->get();

        $passes = Pass::query()
            ->whereIn('order_id', $orders->pluck('id'))
            ->orWhereIn('id', $orders->pluck('upgrades_pass_id')->filter())
            ->get();

        $total = 0.0;

        foreach ($orders as $order) {
            $pass = $order->upgrades_pass_id
                ? $passes->firstWhere('id', $order->upgrades_pass_id)
                : $passes->firstWhere('order_id', $order->id);

            // An upgrade pays for the days from its payment to the end of the pass.
            $from = $order->upgrades_pass_id ? $order->paid_at : $pass?->starts_at;
            $to = $pass?->ends_at;

            if (! $from || ! $to || $to <= $from) {
                continue; // e.g. a pass waiting for the launch date
            }

            $overlap = $this->overlapSeconds($from, $to, $start, $end);

            if ($overlap > 0) {
                $share = in_array($order->plan_id, $plus, true) ? $plusShare : 1;
                $total += $order->amount_sen * $share * $overlap / $from->diffInSeconds($to);
            }
        }

        return (int) round($total);
    }

    /** @return Collection<int, int> book id => family children who read it for long enough */
    public function reads(Carbon $start, Carbon $end): Collection
    {
        $minimum = (int) Setting::get('royalty_min_minutes');

        return ReadingSession::query()
            ->join('children', 'children.id', '=', 'reading_sessions.child_id')
            ->whereNotNull('children.user_id')
            ->whereDate('read_on', '>=', $start)->whereDate('read_on', '<=', $end)
            ->groupBy('reading_sessions.book_id', 'reading_sessions.child_id')
            ->havingRaw('SUM(reading_sessions.minutes) >= ?', [max(1, $minimum)])
            ->get(['reading_sessions.book_id'])
            ->countBy('book_id');
    }

    /** @return Collection<int, int> book id => single sales (sen) paid in the month */
    public function singleSales(Carbon $start, Carbon $end): Collection
    {
        return Order::query()
            ->where('status', OrderStatus::Paid)
            ->whereNotNull('book_id')
            ->whereBetween('paid_at', [$start, $end])
            ->get(['book_id', 'amount_sen'])
            ->groupBy('book_id')
            ->map(fn (Collection $orders) => (int) $orders->sum('amount_sen'));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $books
     * @return Collection<int, array{name: string, role: string, bank: string, books: int, total_sen: int}>
     */
    private function byContributor(Collection $books): Collection
    {
        $rows = [];

        foreach ($books as $row) {
            foreach ($row['book']->contributors as $contributor) {
                $rows[$contributor->id] ??= [
                    'name' => $contributor->name,
                    'roles' => [],
                    'bank' => trim("{$contributor->bank_name} {$contributor->bank_account_number}"),
                    'books' => 0,
                    'total' => 0.0,
                ];
                $rows[$contributor->id]['roles'][$contributor->pivot->role] = true;
                $rows[$contributor->id]['books']++;
                $rows[$contributor->id]['total'] += $row['total_sen'] * $contributor->pivot->share_percent / 100;
            }
        }

        return collect($rows)
            ->map(fn (array $r) => [
                'name' => $r['name'],
                'role' => collect(array_keys($r['roles']))->map(fn ($role) => ContributorRole::tryFrom($role)?->getLabel() ?? $role)->join(', '),
                'bank' => $r['bank'],
                'books' => $r['books'],
                'total_sen' => (int) round($r['total']),
            ])
            ->sortByDesc('total_sen')
            ->values();
    }

    private function overlapSeconds(Carbon $from, Carbon $to, Carbon $start, Carbon $end): int
    {
        $a = $from->max($start);
        $b = $to->min($end);

        return $b > $a ? (int) $a->diffInSeconds($b) : 0;
    }
}
