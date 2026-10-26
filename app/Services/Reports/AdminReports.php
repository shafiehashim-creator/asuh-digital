<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Child;
use App\Models\Order;
use App\Models\PageVisit;
use App\Models\Pass;
use App\Models\Preorder;
use App\Models\ReadingSession;
use App\Models\User;
use App\Support\SalesChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Figures for the admin "Laporan" page: sales, products, channels, customers
 * and traffic over a period. Amounts are in sen.
 */
class AdminReports
{
    private function paid(Period $period): Builder
    {
        return Order::query()->where('status', OrderStatus::Paid)->whereBetween('paid_at', $period->range());
    }

    // --- Sales ------------------------------------------------------------------------

    /** @return array{revenue: int, orders: int, average: int, discounts: int} */
    public function sales(Period $period): array
    {
        $orders = $this->paid($period)->get(['amount_sen', 'discount_sen']);

        return [
            'revenue' => (int) $orders->sum('amount_sen'),
            'orders' => $orders->count(),
            'average' => $orders->count() ? intdiv((int) $orders->sum('amount_sen'), $orders->count()) : 0,
            'discounts' => (int) $orders->sum('discount_sen'),
        ];
    }

    /** Percentage change against the period before, or null when there is nothing to compare. */
    public static function change(int|float $now, int|float $before): ?float
    {
        return $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
    }

    /**
     * Revenue per day, split by kind of product.
     *
     * @return array{labels: list<string>, series: array<string, list<int>>}
     */
    public function dailySales(Period $period): array
    {
        $dates = $period->dates();
        $series = ['Langganan' => array_fill_keys($dates, 0), 'Buku single' => array_fill_keys($dates, 0), 'Naik taraf' => array_fill_keys($dates, 0)];

        foreach ($this->paid($period)->get(['paid_at', 'amount_sen', 'book_id', 'upgrades_pass_id']) as $order) {
            $kind = match (true) {
                (bool) $order->book_id => 'Buku single',
                (bool) $order->upgrades_pass_id => 'Naik taraf',
                default => 'Langganan',
            };
            $series[$kind][$order->paid_at->toDateString()] += $order->amount_sen;
        }

        return ['labels' => $dates, 'series' => array_map('array_values', $series)];
    }

    // --- Products -----------------------------------------------------------------------

    /** @return Collection<int, array{product: string, units: int, revenue: int, share: float}> */
    public function products(Period $period): Collection
    {
        $orders = $this->paid($period)->with(['plan', 'book'])->get();
        $total = max(1, (int) $orders->sum('amount_sen'));

        return $orders->groupBy(fn (Order $o) => $o->itemName())
            ->map(fn ($rows, $name) => [
                'product' => $name,
                'units' => $rows->count(),
                'revenue' => (int) $rows->sum('amount_sen'),
                'share' => round($rows->sum('amount_sen') / $total * 100, 1),
            ])
            ->sortByDesc('revenue')->values();
    }

    /** Books opened most in Mod Anak during the period (children and pupils). @return Collection<int, array{book: string, readers: int, minutes: int}> */
    public function mostRead(Period $period, int $limit = 10): Collection
    {
        return ReadingSession::query()
            ->whereBetween('read_on', [$period->from->toDateString(), $period->to->toDateString()])
            ->with('book')
            ->get()
            ->groupBy('book_id')
            ->map(fn ($rows) => [
                'book' => $rows->first()->book?->title ?? '-',
                'readers' => $rows->pluck('child_id')->unique()->count(),
                'minutes' => (int) $rows->sum('minutes'),
            ])
            ->sortByDesc('minutes')->take($limit)->values();
    }

    // --- Channels -----------------------------------------------------------------------

    /** The channel an order is credited to. */
    public static function channelOf(Order $order): string
    {
        return match (true) {
            $order->payment_method === PaymentMethod::Manual => SalesChannel::MANUAL,
            (bool) $order->referral_institution_id => SalesChannel::SCHOOL,
            (bool) $order->affiliate_id => SalesChannel::AFFILIATE,
            (bool) $order->coupon_id => SalesChannel::COUPON,
            default => SalesChannel::from($order->source, $order->medium),
        };
    }

    /** @return Collection<int, array{channel: string, orders: int, revenue: int, share: float}> */
    public function channels(Period $period): Collection
    {
        $orders = $this->paid($period)->get(['amount_sen', 'payment_method', 'referral_institution_id', 'source', 'medium']);
        $total = max(1, (int) $orders->sum('amount_sen'));

        return $orders->groupBy(fn (Order $o) => self::channelOf($o))
            ->map(fn ($rows, $channel) => ['channel' => $channel, 'orders' => $rows->count(), 'revenue' => (int) $rows->sum('amount_sen'), 'share' => round($rows->sum('amount_sen') / $total * 100, 1)])
            ->sortByDesc('revenue')->values();
    }

    /** UTM campaigns and sources behind paid orders. @return Collection<int, array{source: string, campaign: string, orders: int, revenue: int}> */
    public function campaigns(Period $period): Collection
    {
        return $this->paid($period)->whereNotNull('source')->where('source', '!=', 'terus')->get(['amount_sen', 'source', 'medium', 'campaign'])
            ->groupBy(fn (Order $o) => $o->source.'|'.$o->campaign)
            ->map(fn ($rows) => [
                'source' => $rows->first()->source.($rows->first()->medium ? ' / '.$rows->first()->medium : ''),
                'campaign' => $rows->first()->campaign ?? '-',
                'orders' => $rows->count(),
                'revenue' => (int) $rows->sum('amount_sen'),
            ])
            ->sortByDesc('revenue')->values();
    }

    /** @return Collection<int, array{method: string, orders: int, revenue: int}> */
    public function paymentMethods(Period $period): Collection
    {
        return $this->paid($period)->get(['amount_sen', 'payment_method'])
            ->groupBy(fn (Order $o) => $o->payment_method->getLabel())
            ->map(fn ($rows, $method) => ['method' => $method, 'orders' => $rows->count(), 'revenue' => (int) $rows->sum('amount_sen')])
            ->sortByDesc('revenue')->values();
    }

    /** Family orders that came with a school's parent code. @return Collection<int, array{school: string, orders: int, revenue: int, discounts: int}> */
    public function schools(Period $period): Collection
    {
        return $this->paid($period)->whereNotNull('referral_institution_id')->with('referralInstitution')->get()
            ->groupBy('referral_institution_id')
            ->map(fn ($rows) => [
                'school' => $rows->first()->referralInstitution?->name ?? '-',
                'orders' => $rows->count(),
                'revenue' => (int) $rows->sum('amount_sen'),
                'discounts' => (int) $rows->sum('discount_sen'),
            ])
            ->sortByDesc('revenue')->values();
    }

    // --- Customers ----------------------------------------------------------------------

    /** @return array{new_accounts: int, buyers: int, first_time_buyers: int, repeat_buyers: int, conversion: ?float, active_subscriptions: int, children: int, reading_families: int} */
    public function customers(Period $period): array
    {
        $newAccounts = User::query()->where('role', Role::Parent)->whereBetween('created_at', $period->range());
        $buyers = $this->paid($period)->distinct()->pluck('user_id');
        $firstPaid = Order::query()->where('status', OrderStatus::Paid)->whereIn('user_id', $buyers)
            ->selectRaw('user_id, MIN(paid_at) as first_paid')->groupBy('user_id')->pluck('first_paid', 'user_id');
        $paidCounts = Order::query()->where('status', OrderStatus::Paid)->whereIn('user_id', $buyers)
            ->selectRaw('user_id, COUNT(*) as total')->groupBy('user_id')->pluck('total', 'user_id');
        $newCount = (clone $newAccounts)->count();
        $newThatPaid = (clone $newAccounts)->whereHas('orders', fn ($q) => $q->where('status', OrderStatus::Paid))->count();

        return [
            'new_accounts' => $newCount,
            'buyers' => $buyers->count(),
            'first_time_buyers' => $firstPaid->filter(fn ($at) => $at >= $period->from->toDateTimeString())->count(),
            'repeat_buyers' => $paidCounts->filter(fn ($n) => $n > 1)->count(),
            'conversion' => $newCount ? round($newThatPaid / $newCount * 100, 1) : null,
            'active_subscriptions' => Pass::query()->active()->distinct()->count('user_id'),
            'children' => Child::query()->whereNotNull('user_id')->whereBetween('created_at', $period->range())->count(),
            'reading_families' => ReadingSession::query()
                ->whereBetween('read_on', [$period->from->toDateString(), $period->to->toDateString()])
                ->join('children', 'children.id', '=', 'reading_sessions.child_id')->whereNotNull('children.user_id')
                ->distinct()->count('children.user_id'),
        ];
    }

    /** @return array{labels: list<string>, accounts: list<int>, buyers: list<int>} */
    public function dailyCustomers(Period $period): array
    {
        $dates = $period->dates();
        $accounts = array_fill_keys($dates, 0);
        $buyers = array_fill_keys($dates, 0);

        foreach (User::query()->where('role', Role::Parent)->whereBetween('created_at', $period->range())->pluck('created_at') as $at) {
            $accounts[$at->toDateString()]++;
        }
        foreach (Order::query()->where('status', OrderStatus::Paid)->selectRaw('user_id, MIN(paid_at) as first_paid')->groupBy('user_id')->pluck('first_paid') as $at) {
            $day = substr((string) $at, 0, 10);
            if (isset($buyers[$day])) {
                $buyers[$day]++;
            }
        }

        return ['labels' => $dates, 'accounts' => array_values($accounts), 'buyers' => array_values($buyers)];
    }

    /** Families by state, from the pre-order form (the only place a state is asked). @return Collection<int, array{state: string, customers: int}> */
    public function states(): Collection
    {
        return Preorder::query()->whereNotNull('state')->selectRaw('state, COUNT(DISTINCT user_id) as total')
            ->groupBy('state')->orderByDesc('total')->get()
            ->map(fn ($row) => ['state' => $row->state, 'customers' => (int) $row->total]);
    }

    // --- Traffic -------------------------------------------------------------------------

    private function visits(Period $period): Builder
    {
        return PageVisit::query()->whereBetween('visited_at', $period->range());
    }

    /** @return array{views: int, visitors: int, signups: int, conversion: ?float} */
    public function traffic(Period $period): array
    {
        $visitors = $this->visits($period)->distinct()->count('visitor');
        $paid = $this->paid($period)->count();

        return [
            'views' => $this->visits($period)->count(),
            'visitors' => $visitors,
            'signups' => User::query()->where('role', Role::Parent)->whereBetween('created_at', $period->range())->count(),
            'conversion' => $visitors ? round($paid / $visitors * 100, 2) : null,
        ];
    }

    /** @return array{labels: list<string>, views: list<int>, visitors: list<int>} */
    public function dailyTraffic(Period $period): array
    {
        $dates = $period->dates();
        $views = array_fill_keys($dates, 0);
        $visitors = array_fill_keys($dates, []);

        foreach ($this->visits($period)->get(['visited_at', 'visitor']) as $visit) {
            $day = $visit->visited_at->toDateString();
            $views[$day]++;
            $visitors[$day][$visit->visitor] = true;
        }

        return ['labels' => $dates, 'views' => array_values($views), 'visitors' => array_values(array_map('count', $visitors))];
    }

    /** @return Collection<int, array{channel: string, visitors: int, orders: int, revenue: int}> */
    public function trafficSources(Period $period): Collection
    {
        $visitors = $this->visits($period)->get(['visitor', 'source', 'medium'])->unique('visitor')
            ->groupBy(fn ($v) => SalesChannel::from($v->source, $v->medium))->map->count();
        $orders = $this->channels($period)->keyBy('channel');

        return $visitors->keys()->merge($orders->keys())->unique()
            ->map(fn ($channel) => [
                'channel' => $channel,
                'visitors' => (int) ($visitors[$channel] ?? 0),
                'orders' => (int) ($orders[$channel]['orders'] ?? 0),
                'revenue' => (int) ($orders[$channel]['revenue'] ?? 0),
            ])
            ->sortByDesc('visitors')->values();
    }

    /** @return Collection<int, array{path: string, views: int, visitors: int}> */
    public function topPages(Period $period, int $limit = 10): Collection
    {
        return $this->visits($period)->selectRaw('path, COUNT(*) as views, COUNT(DISTINCT visitor) as visitors')
            ->groupBy('path')->orderByDesc('views')->limit($limit)->get()
            ->map(fn ($row) => ['path' => $row->path, 'views' => (int) $row->views, 'visitors' => (int) $row->visitors]);
    }

    /** @return array<string, int> visitors per device */
    public function devices(Period $period): array
    {
        return $this->visits($period)->get(['visitor', 'device'])->unique('visitor')->groupBy('device')->map->count()->sortDesc()->all();
    }

    /**
     * From visit to payment. Steps count visitors (or accounts) in the period.
     *
     * @return list<array{step: string, count: int}>
     */
    public function funnel(Period $period): array
    {
        $visits = $this->visits($period);
        $visitors = (clone $visits)->distinct()->count('visitor');
        $sawOffer = (clone $visits)->where(fn ($q) => $q->whereIn('route', ['pass.show', 'catalog.show', 'purchase.pass', 'purchase.plus', 'purchase.book', 'preorder.create']))
            ->distinct()->count('visitor');
        $checkout = Order::query()->whereBetween('created_at', $period->range())->where('payment_method', '!=', PaymentMethod::Manual)->distinct()->count('user_id');
        $paid = $this->paid($period)->where('payment_method', '!=', PaymentMethod::Manual)->distinct()->count('user_id');

        return [
            ['step' => 'Pelawat', 'count' => $visitors],
            ['step' => 'Lihat tawaran (halaman langganan, buku atau checkout)', 'count' => $sawOffer],
            ['step' => 'Buat pesanan', 'count' => $checkout],
            ['step' => 'Bayar', 'count' => $paid],
        ];
    }
}
