@php
    use App\Enums\PassStartRule;
    use App\Services\CatalogStats;
    use App\Support\Money;

    // Early bird first; once its slots are gone the regular pass is sold instead.
    $earlyBird = $offer->earlyBird();
    $onSale = $offer->available();
    $checkoutUrl = $offer->checkoutUrl();
    $ctaLabel = $offer->ctaLabel();
    $pilotUrl = Route::has('pilot.create') ? route('pilot.create') : null;

    // Only numbers worth showing (a "0 buku" strip would discourage).
    $statItems = array_filter([
        $stats['families'] >= CatalogStats::MIN_FAMILIES ? [number_format($stats['families']).'+', 'Keluarga melanggan'] : null,
        $catalogOpen && $stats['books'] > 0 ? [number_format($stats['books']), 'E-book Islamik'] : null,
        $catalogOpen && $stats['pages'] > 0 ? [number_format($stats['pages']).'+', 'Halaman bacaan'] : null,
        $catalogOpen && $stats['categories'] > 0 ? [$stats['categories'], 'Kategori'] : null,
    ]);
    $shelfRow = '-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2';
@endphp

<x-layouts.app>
    {{-- Hero --}}
    <section class="overflow-hidden">
        <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-12 md:grid-cols-[1.1fr_1fr] md:py-16">
            <div>
                <p class="inline-block rounded-full border-2 border-ink bg-oren px-3 py-1 text-sm font-extrabold text-ink">
                    {{ $earlyBird ? 'Pre-order · Harga early bird' : 'Langganan Tahunan · Seisi keluarga' }}
                </p>
                <h1 class="mt-4 font-display text-4xl leading-tight font-extrabold text-magenta sm:text-5xl">{{ $headline }}</h1>
                <p class="mt-4 text-lg text-ink/80">{{ $subheadline }}</p>

                @if ($onSale)
                    <div class="mt-6 flex flex-wrap items-end gap-x-4 gap-y-1">
                        <p class="font-display text-5xl font-extrabold text-plum">{{ Money::short($offer->priceSen()) }}</p>
                        @if ($offer->compareAtSen())
                            <p class="pb-2 text-xl font-bold text-ink/70 line-through" aria-label="Harga biasa {{ Money::short($offer->compareAtSen()) }}">
                                {{ Money::short($offer->compareAtSen()) }}
                            </p>
                        @endif
                        <p class="w-full text-sm font-semibold text-ink/70">Langganan Tahunan · 12 bulan · sekali bayar, tiada caj automatik</p>
                    </div>
                @endif

                @if ($earlyBird || ! $onSale)
                    <x-slot-counter :limit="$offer->slotLimit()" :remaining="$offer->slotsRemaining()" class="mt-6 max-w-md" />
                @endif

                <div class="mt-6 flex flex-wrap gap-3">
                    @if ($onSale)
                        <a href="{{ $checkoutUrl }}" class="btn-utama text-lg">{{ $ctaLabel }}</a>
                    @else
                        <span class="btn-utama" aria-disabled="true">Slot early bird telah habis</span>
                    @endif
                    @if ($catalogOpen)
                        <a href="{{ route('catalog.index') }}" class="btn-gelap">Lihat semua buku</a>
                    @else
                        <a href="#soalan" class="btn-gelap">Soalan lazim</a>
                    @endif
                </div>
            </div>

            <div class="relative mx-auto grid w-full max-w-md grid-cols-2 gap-4">
                <img src="{{ Vite::asset('resources/images/brand/anis.webp') }}" alt="Anis, watak baik ASUH"
                    width="640" height="960" class="aspect-[2/3] w-full rounded-3xl border-2 border-ink object-cover shadow-[0_4px_0_var(--color-ink)]">
                <img src="{{ Vite::asset('resources/images/brand/anas.webp') }}" alt="Anas, watak baik ASUH"
                    width="640" height="960" class="mt-10 aspect-[2/3] w-full rounded-3xl border-2 border-ink object-cover shadow-[0_4px_0_var(--color-ink)]">
            </div>
        </div>
    </section>

    {{-- Numbers --}}
    @if ($statItems)
        <section aria-label="ASUH Digital dalam angka" class="border-y-2 border-ink bg-plum text-white">
            <dl class="mx-auto grid max-w-6xl gap-6 px-4 py-8 text-center {{ [1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-3', 4 => 'grid-cols-2 sm:grid-cols-4'][count($statItems)] }}">
                @foreach ($statItems as [$number, $label])
                    <div>
                        <dt class="sr-only">{{ $label }}</dt>
                        <dd class="font-display text-4xl font-extrabold text-kuning">{{ $number }}</dd>
                        <dd class="text-sm font-bold text-white/90">{{ $label }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    {{-- Most read (or picks, before anyone has read) --}}
    @if ($popular && $popular['books']->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 pt-14" aria-labelledby="popular-heading">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <p class="text-sm font-extrabold tracking-wide text-oren uppercase">{{ $popular['ranked'] ? '🔥 Paling popular' : '⭐ Pilihan kami' }}</p>
                    <h2 id="popular-heading" class="font-display text-3xl font-extrabold text-plum">{{ $popular['ranked'] ? 'Buku kegemaran keluarga' : 'Mula dengan buku-buku ini' }}</h2>
                </div>
                <a href="{{ route('catalog.index') }}" class="pautan text-sm font-bold">Semua buku &rarr;</a>
            </div>
            <ul class="{{ $shelfRow }} mt-6">
                @foreach ($popular['books'] as $i => $book)
                    <li class="w-40 shrink-0 snap-start sm:w-48">
                        <x-book-card :book="$book" :rank="$popular['ranked'] && $book->readers_count > 0 ? $i + 1 : null" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Categories --}}
    <section aria-label="Kategori buku" @class(['mx-auto max-w-6xl px-4 pt-14' => $catalogOpen, 'border-b-2 border-ink bg-plum' => ! $catalogOpen, 'border-t-2' => ! $catalogOpen && ! $statItems])>
        @if ($catalogOpen)
            <h2 class="font-display text-2xl font-extrabold text-plum">Pilih ikut kategori</h2>
            <ul class="mt-4 flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('catalog.index', ['kategori' => $category->slug]) }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-ink bg-white px-4 text-sm font-bold hover:bg-lavender">{{ $category->name }}</a>
                    </li>
                @endforeach
            </ul>
        @else
            <ul class="mx-auto flex max-w-6xl flex-wrap justify-center gap-2 px-4 py-5">
                @foreach ($categories as $category)
                    <li class="rounded-full bg-white/10 px-4 py-1.5 text-sm font-bold text-white">{{ $category->name }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Newest --}}
    @if ($newest->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 pt-14" aria-labelledby="newest-heading">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <p class="text-sm font-extrabold tracking-wide text-biru uppercase">Baru diterbitkan</p>
                    <h2 id="newest-heading" class="font-display text-3xl font-extrabold text-plum">Buku terkini</h2>
                </div>
                <a href="{{ route('catalog.index') }}" class="pautan text-sm font-bold">Semua buku &rarr;</a>
            </div>
            <ul class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-4">
                @foreach ($newest as $book)
                    <li><x-book-card :book="$book" /></li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Why ASUH --}}
    <section class="mx-auto max-w-6xl px-4 py-16">
        <p class="text-center text-sm font-extrabold tracking-wide text-oren uppercase">Kenapa ASUH Digital</p>
        <h2 class="mt-1 text-center font-display text-3xl font-extrabold text-plum sm:text-4xl">Kenapa ibu bapa pilih ASUH Digital</h2>
        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['🛡️', 'Disemak syariah', 'Setiap buku mesti lulus semakan syariah sebelum diterbitkan.'],
                ['🎯', 'Ikut umur anak', 'Rak buku disusun untuk 6–9 tahun dan 10–13 tahun, jadi anak jumpa bacaan yang sesuai.'],
                ['📖', 'Selak macam buku sebenar', 'Mod flip dua muka di tablet dan komputer, satu muka di telefon. Tanpa perlu muat turun aplikasi.'],
                ['⚡', 'Akses serta-merta', 'Bayar melalui FPX, kad atau e-wallet dan buku terus terbuka dalam akaun anda.'],
                ['👨‍👩‍👧', 'Seisi keluarga', 'Satu langganan untuk sehingga 4 profil anak, di telefon, tablet atau komputer.'],
                ['🧒', 'Mod Anak yang selamat', 'Tiada harga, butang beli atau tetapan. Keluar hanya melalui pintu ibu bapa.'],
            ] as [$icon, $title, $body])
                <div class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
                    <p class="text-3xl" aria-hidden="true">{{ $icon }}</p>
                    <h3 class="mt-2 font-display text-xl font-bold text-plum">{{ $title }}</h3>
                    <p class="mt-2 text-ink/80">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Characters --}}
    <section class="bg-lavender">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <h2 class="text-center font-display text-3xl font-extrabold text-plum sm:text-4xl">Kenali watak-watak ASUH</h2>
            <p class="mx-auto mt-3 max-w-2xl text-center text-ink/80">
                Anis dan Anas mengajak anak memilih yang baik. Para penggoda pula cuba memesongkan mereka.
                Setiap kisah membantu anak mengenal beza antara keduanya. Jom Jadi Baik!
            </p>
            <ul class="mt-10 grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach ([
                    ['anis', 'Anis', 'Watak baik', 'bg-biru text-white'],
                    ['anas', 'Anas', 'Watak baik', 'bg-biru text-white'],
                    ['syaitan-gemuk', 'Si Gemuk', 'Penggoda', 'bg-oren text-ink'],
                    ['syaitan-kurus', 'Si Kurus', 'Penggoda', 'bg-oren text-ink'],
                ] as [$image, $name, $label, $labelClass])
                    <li class="overflow-hidden rounded-3xl border-2 border-ink bg-white shadow-[0_4px_0_var(--color-ink)]">
                        <img src="{{ Vite::asset("resources/images/brand/{$image}.webp") }}" alt="{{ $name }}, {{ Str::lower($label) }}"
                            loading="lazy" class="aspect-[3/4] w-full object-cover object-top">
                        <div class="flex items-center justify-between gap-2 border-t-2 border-ink p-3">
                            <p class="font-display font-bold">{{ $name }}</p>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-extrabold {{ $labelClass }}">{{ $label }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Pricing --}}
    <section id="harga" class="mx-auto max-w-6xl scroll-mt-4 px-4 py-16">
        <div class="mx-auto max-w-xl rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_6px_0_var(--color-ink)] sm:p-8">
            <p class="inline-block rounded-full border-2 border-ink bg-oren px-3 py-0.5 text-sm font-extrabold text-ink">{{ $earlyBird ? 'Harga early bird' : 'Harga Langganan Tahunan' }}</p>
            <h2 class="mt-2 font-display text-3xl font-extrabold text-plum">Langganan Tahunan ASUH Digital</h2>

            @if ($onSale)
                <p class="mt-4 font-display text-5xl font-extrabold text-magenta">
                    {{ Money::short($offer->priceSen()) }}
                    @if ($offer->compareAtSen())
                        <span class="text-2xl text-ink/70 line-through">{{ Money::short($offer->compareAtSen()) }}</span>
                    @endif
                </p>
            @endif

            <ul class="mt-6 space-y-3">
                @foreach ([
                    'Akses semua e-book ASUH Digital selama 12 bulan',
                    'Sehingga 4 profil anak dalam satu akaun',
                    'Mod Anak dengan pintu ibu bapa',
                    'Bayaran sekali sahaja, tiada caj automatik',
                ] as $benefit)
                    <li class="flex gap-3">
                        <span aria-hidden="true" class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-biru text-sm font-bold text-white">✓</span>
                        <span>{{ $benefit }}</span>
                    </li>
                @endforeach
            </ul>

            @if ($earlyBird || ! $onSale)
                <x-slot-counter :limit="$offer->slotLimit()" :remaining="$offer->slotsRemaining()" class="mt-6" />
            @endif

            @if ($onSale)
                <a href="{{ $checkoutUrl }}" class="btn-utama mt-6 w-full text-lg">{{ $ctaLabel }}</a>
                <a href="{{ route('pass.show') }}" class="pautan mt-4 block text-center text-sm font-bold">Kenapa Langganan Tahunan lebih jimat? &rarr;</a>
            @else
                <p class="mt-6 rounded-xl bg-ink/5 p-4 font-semibold">
                    Slot early bird telah habis. Terima kasih atas sokongan anda! Langganan Tahunan akan dijual pada harga biasa apabila platform dilancarkan.
                </p>
            @endif
        </div>
    </section>

    {{-- Institutions --}}
    <section class="bg-plum text-white">
        <div class="mx-auto flex max-w-6xl flex-col items-start gap-6 px-4 py-14 md:flex-row md:items-center md:justify-between">
            <div class="max-w-2xl">
                <h2 class="font-display text-3xl font-extrabold">Untuk tadika, SRA, KAFA dan tahfiz</h2>
                <p class="mt-3 text-white/90">
                    Cuba ASUH Digital bersama murid anda secara percuma selama <strong class="text-kuning">{{ $pilotDays }} hari</strong>
                    melalui program pilot institusi.
                </p>
            </div>
            @if ($pilotUrl)
                <a href="{{ $pilotUrl }}" class="btn-utama shrink-0">Mohon pilot percuma</a>
            @endif
        </div>
    </section>

    {{-- FAQ --}}
    <section id="soalan" class="mx-auto max-w-3xl scroll-mt-4 px-4 py-16">
        <h2 class="text-center font-display text-3xl font-extrabold text-plum sm:text-4xl">Soalan lazim</h2>

        <div class="mt-8 space-y-3">
            @foreach ($faq as $item)
                <x-faq-item :question="$item['question']">{{ $item['answer'] }}</x-faq-item>
            @endforeach

            <x-faq-item question="Bila Langganan Tahunan saya bermula?">
                @if ($passStartRule === PassStartRule::OnLaunch)
                    Tempoh 12 bulan bermula pada tarikh platform ASUH Digital dilancarkan, jadi anda tidak kehilangan sebarang bulan. Kami akan maklumkan tarikhnya melalui e-mel.
                @else
                    Tempoh 12 bulan bermula sebaik sahaja bayaran anda disahkan.
                @endif
            </x-faq-item>

            <x-faq-item question="Boleh saya minta bayaran balik?">{{ $refundPolicy }}</x-faq-item>
        </div>
    </section>

    {{-- Final call to action --}}
    @if ($onSale)
        <section class="mx-auto max-w-6xl px-4 pb-4">
            <div class="rounded-3xl border-2 border-ink bg-kuning p-8 text-center shadow-[0_6px_0_var(--color-ink)]">
                <h2 class="font-display text-3xl font-extrabold text-ink">Jom Jadi Baik bersama ASUH!</h2>
                <p class="mt-2 font-semibold text-ink/80">{{ $earlyBird ? 'Tempah slot early bird sebelum habis.' : 'Satu langganan untuk seisi keluarga, semua e-book selama 12 bulan.' }}</p>
                <a href="{{ $checkoutUrl }}" class="btn-gelap mt-6 text-lg">{{ $ctaLabel }}</a>
            </div>
        </section>
    @endif
</x-layouts.app>
