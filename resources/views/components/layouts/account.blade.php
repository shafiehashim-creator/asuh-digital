@props(['title', 'heading' => null, 'intro' => null, 'activePass' => null, 'pendingPass' => null, 'shelfOpen' => false, 'plusUpgradePrice' => null])

@php
    use App\Support\Money;

    // The account side menu. Each item: label, route, active routes, icon paths, shown?
    $menu = array_filter([
        ['Ringkasan', 'account', ['account'], ['M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z'], true],
        ['Rak buku saya', 'account.shelf', ['account.shelf'], ['M4 4h5a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H4z', 'M20 4h-5a3 3 0 0 0-3 3v13a2 2 0 0 1 2-2h6z'], $shelfOpen],
        ['Anak-anak', 'account.children', ['account.children', 'children.*'], ['M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M2 21a7 7 0 0 1 14 0', 'M16 3.5a4 4 0 0 1 0 7.5', 'M22 21a7 7 0 0 0-4-6.3'], $shelfOpen],
        ['Pencapaian', 'account.progress', ['account.progress'], ['M12 15a6 6 0 1 0 0-12 6 6 0 0 0 0 12', 'm8.5 13.5-1.5 8 5-3 5 3-1.5-8'], $shelfOpen],
        ['Langganan dan pesanan', 'account.billing', ['account.billing'], ['M6 2h12v20l-3-2-3 2-3-2-3 2z', 'M9 7h6', 'M9 11h6', 'M9 15h4'], true],
        ['Profil dan keselamatan', 'account.profile', ['account.profile'], ['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M5 21a7 7 0 0 1 14 0'], true],
        ['Affiliate', 'account.affiliate', ['account.affiliate'], ['M18 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6', 'M6 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6', 'M18 22a3 3 0 1 0 0-6 3 3 0 0 0 0 6', 'm8.6 13.5 6.8 4', 'm15.4 6.5-6.8 4'],
            app(App\Services\Affiliates::class)->isOpen() || auth()->user()?->affiliate()->exists()],
    ], fn ($item) => $item[4]);

    // One offer at a time, matched to the family's subscription.
    $offer = match (true) {
        $plusUpgradePrice !== null => ['Baharu: video animasi', 'Naik taraf ke Langganan Plus. Bayar beza harga untuk baki langganan sahaja.', route('purchase.plus'), 'Naik taraf · '.Money::short($plusUpgradePrice)],
        $activePass === null && $pendingPass === null => ['Buka semua buku', 'Langganan Tahunan membuka semua e-book ASUH untuk semua anak anda.', route('purchase.pass'), 'Lihat langganan'],
        $activePass !== null && $activePass->ends_at->lessThan(now()->addDays(60)) => ['Langganan hampir tamat', 'Perbaharui sekarang. Langganan baharu bermula sebaik langganan semasa tamat.', route('purchase.pass'), 'Perbaharui'],
        default => null,
    };
@endphp

<x-layouts.app :title="$title">
    <div class="mx-auto max-w-6xl px-4 py-6 lg:grid lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-8 lg:py-10">
        <aside class="lg:sticky lg:top-6 lg:self-start">
            <nav aria-label="Menu akaun">
                <ul class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-2 lg:mx-0 lg:block lg:space-y-1 lg:overflow-visible lg:rounded-3xl lg:border-2 lg:border-ink/15 lg:bg-white lg:p-3 lg:pb-3">
                    @foreach ($menu as [$label, $route, $active, $icon])
                        @php($current = request()->routeIs(...$active))
                        <li class="shrink-0">
                            <a href="{{ route($route) }}" @if ($current) aria-current="page" @endif
                                @class([
                                    'flex min-h-11 items-center gap-3 rounded-full px-4 text-sm font-bold whitespace-nowrap lg:rounded-2xl lg:px-3',
                                    'bg-plum text-white lg:bg-lavender lg:text-plum' => $current,
                                    'border-2 border-ink/15 bg-white text-ink/80 hover:text-plum lg:border-0 lg:hover:bg-latar' => ! $current,
                                ])>
                                <svg viewBox="0 0 24 24" class="hidden size-5 shrink-0 lg:block" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    @foreach ($icon as $d)
                                        <path d="{{ $d }}" />
                                    @endforeach
                                </svg>
                                {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            @if ($offer)
                <div class="mt-4 hidden rounded-3xl border-2 border-ink bg-plum p-5 text-white shadow-[0_4px_0_var(--color-ink)] lg:block">
                    <p class="font-display text-lg leading-tight font-bold text-kuning">{{ $offer[0] }}</p>
                    <p class="mt-2 text-sm text-white/85">{{ $offer[1] }}</p>
                    <a href="{{ $offer[2] }}" class="btn-utama mt-4 w-full px-4 text-sm">{{ $offer[3] }}</a>
                </div>
            @endif

            <form method="POST" action="{{ route('logout') }}" class="mt-4 hidden lg:block">
                @csrf
                <button type="submit" class="flex min-h-11 w-full items-center gap-3 rounded-2xl px-3 text-sm font-bold text-ink/70 hover:bg-white hover:text-plum">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="m16 17 5-5-5-5" /><path d="M21 12H9" /></svg>
                    Log keluar
                </button>
            </form>
        </aside>

        <div class="mt-4 min-w-0 space-y-6 lg:mt-0">
            @if ($heading)
                <div>
                    <h1 class="font-display text-3xl font-extrabold text-magenta">{{ $heading }}</h1>
                    @if ($intro)
                        <p class="mt-1 text-ink/80">{{ $intro }}</p>
                    @endif
                </div>
            @endif

            {{ $slot }}

            @if ($offer)
                {{-- On phones and tablets the offer sits under the page instead of in the side menu. --}}
                <div class="rounded-3xl border-2 border-ink bg-plum p-5 text-white shadow-[0_4px_0_var(--color-ink)] lg:hidden">
                    <p class="font-display text-lg font-bold text-kuning">{{ $offer[0] }}</p>
                    <p class="mt-1 text-sm text-white/85">{{ $offer[1] }}</p>
                    <a href="{{ $offer[2] }}" class="btn-utama mt-4 px-5 text-sm">{{ $offer[3] }}</a>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
