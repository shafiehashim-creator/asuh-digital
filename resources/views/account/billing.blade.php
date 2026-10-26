@php
    use App\Enums\OrderStatus;
    use App\Support\Money;

    $statusClasses = [
        OrderStatus::Paid->value => 'bg-biru/10 text-biru',
        OrderStatus::Pending->value => 'bg-oren/15 text-ink',
        OrderStatus::Failed->value => 'bg-ink/10 text-ink',
        OrderStatus::Refunded->value => 'bg-ink/10 text-ink',
    ];
@endphp

<x-layouts.account title="Langganan dan pesanan" heading="Langganan dan pesanan" :$activePass :$pendingPass :$shelfOpen :$plusUpgradePrice>
    <section aria-labelledby="pass-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
        <h2 id="pass-heading" class="font-display text-xl font-bold text-plum">Langganan</h2>

        @if ($activePass)
            @php
                $monthly = $activePass->plan?->isMonthly();
                $renewRoute = $activePass->isPlus() ? 'purchase.plus' : 'purchase.pass';
            @endphp
            <p class="mt-2 text-lg">
                <span class="font-bold">{{ $activePass->plan?->name }}</span> ·
                <span class="font-bold text-biru">Aktif</span> sehingga
                <strong>{{ $activePass->ends_at->translatedFormat('j F Y') }}</strong>.
            </p>
            @if ($monthly)
                {{-- Monthly is paid by hand: renew, or better, move to yearly. --}}
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route($renewRoute, ['tempoh' => 'tahunan']) }}" class="btn-utama">Tukar ke tahunan (lebih jimat)</a>
                    <a href="{{ route($renewRoute, ['tempoh' => 'bulanan']) }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-ink/20 px-5 font-bold hover:border-ink">Perbaharui sebulan lagi</a>
                </div>
                <p class="mt-2 text-sm text-ink/70">Tiada caj automatik. Langganan baharu bermula sebaik langganan semasa tamat, jadi tiada hari yang hilang.</p>
            @elseif ($activePass->ends_at->lessThan(now()->addDays(60)))
                <a href="{{ route($renewRoute, ['tempoh' => 'tahunan']) }}" class="btn-utama mt-4">Perbaharui langganan</a>
                <p class="mt-2 text-sm text-ink/70">Langganan baharu bermula sebaik langganan semasa tamat.</p>
            @endif
        @elseif ($pendingPass)
            <p class="mt-2 text-lg">
                Bayaran diterima. Langganan anda akan <strong>bermula pada tarikh platform ASUH Digital dilancarkan</strong>.
                Kami akan maklumkan melalui e-mel.
            </p>
        @else
            <p class="mt-2 text-ink/80">Anda belum mempunyai langganan yang aktif.</p>
            <a href="{{ route('pass.show') }}" class="btn-utama mt-4">Pilih langganan</a>
        @endif
    </section>

    <section aria-labelledby="orders-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
        <h2 id="orders-heading" class="font-display text-xl font-bold text-plum">Pesanan saya</h2>

        @if ($orders->isEmpty())
            <p class="mt-2 text-ink/80">Tiada pesanan lagi.</p>
        @else
            <ul class="mt-4 divide-y-2 divide-ink/10">
                @foreach ($orders as $order)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-4">
                        <div>
                            <p class="font-bold">{{ $order->itemName() }}</p>
                            <p class="text-sm text-ink/70">
                                {{ $order->reference }} · {{ $order->created_at->translatedFormat('j M Y') }} · {{ $order->payment_method->getLabel() }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-bold">{{ Money::format($order->totalSen()) }}</span>
                            <span class="rounded-full px-3 py-1 text-sm font-bold {{ $statusClasses[$order->status->value] }}">
                                {{ $order->status->getLabel() }}
                            </span>
                            @if ($order->status === OrderStatus::Pending)
                                <a href="{{ route('preorder.pay', $order) }}" class="pautan text-sm">Teruskan bayaran</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.account>
