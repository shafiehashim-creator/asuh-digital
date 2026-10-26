@php
    $hasAccess = $activePass !== null || $ownedCount > 0;
    $planName = $activePass?->isPlus() ? 'Langganan Plus' : 'Langganan Tahunan';

    $status = array_filter([
        match (true) {
            $activePass !== null => $planName.' aktif hingga '.$activePass->ends_at->translatedFormat('j M Y'),
            $pendingPass !== null => 'Langganan anda bermula pada tarikh pelancaran',
            default => 'Anda belum melanggan',
        },
        $children->isNotEmpty() ? $children->count().' anak' : null,
        $weekMinutes > 0 ? $weekMinutes.' minit membaca minggu ini' : null,
    ]);

    // Stat cards: label, value, small line, link, icon path.
    $cards = $shelfOpen
        ? [
            ['Langganan', $activePass ? 'Aktif' : ($pendingPass ? 'Menunggu' : 'Tiada'), $activePass ? (int) now()->diffInDays($activePass->ends_at).' hari lagi' : ($pendingPass ? 'Bermula semasa pelancaran' : 'Lihat pilihan langganan'), route('account.billing'), 'M6 2h12v20l-3-2-3 2-3-2-3 2z'],
            ['Anak', $children->count().' / '.App\Models\Child::MAX_PER_ACCOUNT, $children->isEmpty() ? 'Tambah profil anak' : 'Profil anak', route('account.children'), 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M2 21a7 7 0 0 1 14 0'],
            ['Minit membaca', (string) $weekMinutes, 'Minggu ini', route('account.progress'), 'M12 7v5l3 2M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20'],
            ['Lencana', (string) $badgeCount, 'Dikumpul anak-anak', route('account.progress'), 'M12 15a6 6 0 1 0 0-12 6 6 0 0 0 0 12m-3.5-1.5-1.5 8 5-3 5 3-1.5-8'],
        ]
        : [
            ['Langganan', $activePass ? 'Aktif' : ($pendingPass ? 'Menunggu' : 'Tiada'), $pendingPass ? 'Bermula semasa pelancaran' : 'Status langganan', route('account.billing'), 'M6 2h12v20l-3-2-3 2-3-2-3 2z'],
            ['Pesanan', (string) $orderCount, 'Pesanan dan resit', route('account.billing'), 'M9 7h6M9 11h6M9 15h4M6 2h12v20l-3-2-3 2-3-2-3 2z'],
        ];
@endphp

<x-layouts.account title="Akaun saya" :$activePass :$pendingPass :$shelfOpen :$plusUpgradePrice>
    <x-install-app banner />

    {{-- Greeting with the one next step that matters most --}}
    <section class="rounded-3xl border-2 border-ink bg-plum p-6 text-white shadow-[0_4px_0_var(--color-ink)] sm:flex sm:items-center sm:justify-between sm:gap-6">
        <div class="min-w-0">
            <h1 class="font-display text-2xl font-extrabold sm:text-3xl">Assalamualaikum, {{ $user->name }}</h1>
            <p class="mt-1 text-white/85">{{ implode(' · ', $status) }}.</p>
        </div>

        <div class="mt-4 shrink-0 sm:mt-0">
            @if (! $hasAccess && ! $pendingPass)
                <a href="{{ route('purchase.pass') }}" class="btn-utama">Langgan sekarang</a>
            @elseif ($shelfOpen && $children->isEmpty())
                <a href="{{ route('children.create') }}" class="btn-utama">Tambah profil anak</a>
            @elseif ($shelfOpen && $children->count() === 1)
                <form method="POST" action="{{ route('children.child-mode', $children->first()) }}">
                    @csrf
                    <button type="submit" class="btn-utama">▶ Mod Anak: {{ $children->first()->nickname }}</button>
                </form>
            @elseif ($shelfOpen)
                <a href="{{ route('account.children') }}" class="btn-utama">▶ Masuk Mod Anak</a>
            @endif
        </div>
    </section>

    {{-- Stat cards --}}
    <nav aria-label="Ringkasan akaun" @class(['grid gap-3', 'grid-cols-2 md:grid-cols-4' => count($cards) === 4, 'grid-cols-2' => count($cards) === 2])>
        @foreach ($cards as [$label, $value, $line, $href, $icon])
            <a href="{{ $href }}" class="group rounded-3xl border-2 border-ink/15 bg-white p-4 transition hover:border-ink">
                <span class="flex items-center justify-between gap-2">
                    <span class="flex items-center gap-2 text-xs font-extrabold tracking-wide text-ink/70 uppercase">
                        <span class="grid size-8 place-items-center rounded-xl bg-lavender text-biru">
                            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}" /></svg>
                        </span>
                        {{ $label }}
                    </span>
                    <span class="text-ink/40 group-hover:text-plum" aria-hidden="true">›</span>
                </span>
                <span class="mt-3 block font-display text-3xl font-extrabold text-plum">{{ $value }}</span>
                <span class="block text-sm text-ink/70">{{ $line }}</span>
            </a>
        @endforeach
    </nav>

    @if (App\Support\BrandVideo::shownOn('account'))
        <section aria-labelledby="song-heading" class="rounded-3xl border-2 border-ink bg-white p-5 shadow-[0_4px_0_var(--color-ink)] sm:p-6">
            <h2 id="song-heading" class="font-display text-xl font-bold text-plum">{{ App\Support\BrandVideo::title() }}</h2>
            <p class="mt-1 text-sm text-ink/70">Tonton bersama anak, atau buka di Mod Anak supaya mereka boleh menontonnya sendiri.</p>
            <x-brand-video place="account" class="mt-4" />
        </section>
    @endif

    @if ($shelfOpen)
        {{-- Continue reading, with how far each child got --}}
        <section aria-labelledby="continue-heading" class="rounded-3xl border-2 border-ink bg-white p-5 shadow-[0_4px_0_var(--color-ink)] sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="continue-heading" class="font-display text-xl font-bold text-plum">Sambung membaca</h2>
                <a href="{{ route('account.shelf') }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-ink/15 px-4 text-sm font-bold hover:border-ink">Rak buku saya</a>
            </div>

            @if ($continueReading->isNotEmpty())
                <ul class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                    @foreach ($continueReading as $item)
                        <li class="flex min-w-0 items-center gap-3 rounded-2xl border-2 border-ink/15 p-3">
                            <div class="w-16 shrink-0"><x-book-cover :book="$item['book']" size="sm" class="rounded-xl" /></div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-display text-lg leading-tight font-bold">{{ $item['book']->title }}</p>
                                <p class="text-sm text-ink/70">{{ $item['child']->nickname }} · halaman {{ $item['page'] }}</p>
                                <form method="POST" action="{{ route('children.child-mode', $item['child']) }}">
                                    @csrf
                                    <input type="hidden" name="buku" value="{{ $item['book']->slug }}">
                                    <button type="submit" class="inline-flex min-h-11 items-center text-sm font-bold text-biru underline-offset-2 hover:underline"
                                        aria-label="Sambung {{ $item['book']->title }} dalam Mod Anak {{ $item['child']->nickname }}">Sambung membaca →</button>
                                </form>
                            </div>
                            @if ($item['percent'] !== null)
                                <span class="relative grid size-14 shrink-0 place-items-center" role="img" aria-label="{{ $item['percent'] }}% dibaca">
                                    <svg viewBox="0 0 36 36" class="absolute inset-0 -rotate-90" aria-hidden="true">
                                        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="var(--color-lavender)" stroke-width="3.5" />
                                        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="var(--color-biru)" stroke-width="3.5" stroke-linecap="round"
                                            stroke-dasharray="{{ $item['percent'] }} 100" />
                                    </svg>
                                    <span class="text-xs font-extrabold text-biru">{{ $item['percent'] }}%</span>
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @elseif ($recentBooks->isNotEmpty())
                <div class="mt-4 grid grid-cols-3 gap-4 sm:grid-cols-4">
                    @foreach ($recentBooks as $book)
                        <x-shelf-book :book="$book" />
                    @endforeach
                </div>
            @else
                <p class="mt-2 text-ink/80">
                    {{ $canReadAll ? 'Belum ada buku yang dibaca. Masuk Mod Anak atau pilih buku pertama dalam katalog.' : 'Buku yang dibaca akan dipaparkan di sini.' }}
                </p>
                <a href="{{ route('catalog.index') }}" class="btn-gelap mt-4">Lihat katalog</a>
            @endif
        </section>

        {{-- Children at a glance --}}
        @if ($children->isNotEmpty())
            <section aria-labelledby="kids-heading" class="rounded-3xl border-2 border-ink bg-white p-5 shadow-[0_4px_0_var(--color-ink)] sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="kids-heading" class="font-display text-xl font-bold text-plum">Anak-anak minggu ini</h2>
                    <a href="{{ route('account.progress') }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-ink/15 px-4 text-sm font-bold hover:border-ink">Pencapaian</a>
                </div>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($children as $child)
                        @php($week = $weeks[$child->id])
                        <li class="flex items-center gap-3 rounded-2xl bg-latar p-3">
                            <x-child-avatar :avatar="$child->avatar" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-display text-lg font-bold">{{ $child->nickname }}</p>
                                <p class="text-sm text-ink/70">
                                    {{ $week['minutes'] }} minit · {{ count($week['books']) }} buku
                                    @if ($week['badges']) · {{ count($week['badges']) }} lencana baharu @endif
                                </p>
                            </div>
                            <form method="POST" action="{{ route('children.child-mode', $child) }}">
                                @csrf
                                <button type="submit" class="btn-utama px-4 text-sm" aria-label="Mula Mod Anak untuk {{ $child->nickname }}">Mod Anak</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</x-layouts.account>
