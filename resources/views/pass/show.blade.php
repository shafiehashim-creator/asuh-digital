@php
    use App\Enums\PassStartRule;
    use App\Support\Money;

    $onSale = $offer->available();
    $bookCount = $stats['books'];
    // "Matematik mudah" only when buying the priced books one by one really costs more.
    $singleTotal = $stats['single_total_sen'];
    $showMaths = $onSale && $singleTotal > $price;
    $mathsPercent = $showMaths ? (int) floor(100 - ($price / $singleTotal * 100)) : null;
    $perMonth = $onSale ? intdiv($price, 12) : null;
    $perDay = $onSale ? intdiv($price, 365) : null;
    $card = 'rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]';
    $cta = $offer->checkoutUrl();
    $whatsapp = filled(App\Models\Setting::get('whatsapp_number'));
@endphp

<x-layouts.app :title="__('Langganan Tahunan')">
    {{-- 1. Hero: the price first --}}
    <section class="relative overflow-hidden bg-lavender">
        <div class="mx-auto max-w-xl px-4 pt-8 pb-12 text-center">
            <div class="flex items-end justify-center gap-2" aria-hidden="true">
                <img src="{{ Vite::asset('resources/images/brand/anis.webp') }}" alt="" width="96" height="96" class="h-24 w-auto -rotate-3 rounded-2xl border-2 border-ink shadow-[0_3px_0_var(--color-ink)]">
                <img src="{{ Vite::asset('resources/images/brand/anas.webp') }}" alt="" width="96" height="96" class="h-24 w-auto rotate-3 rounded-2xl border-2 border-ink shadow-[0_3px_0_var(--color-ink)]">
            </div>
            <p class="mt-3 inline-block rounded-full border-2 border-ink bg-white px-3 py-1 text-sm font-extrabold text-plum">{{ __('Langganan Tahunan ASUH Digital') }}</p>
            <h1 class="mt-3 font-display text-4xl leading-tight font-extrabold text-magenta sm:text-5xl">{{ __('Tukar masa skrin jadi masa membaca.') }}</h1>
            <p class="mt-3 text-lg text-ink/80">
                @if ($bookCount > 0)
                    {{ __('Semua :count e-book Islamik ASUH, disemak syariah, untuk seisi keluarga selama 12 bulan. Buku baharu terus termasuk.', ['count' => $bookCount]) }}
                @else
                    {{ __('Semua e-book Islamik ASUH untuk anak 6–13 tahun, disemak syariah, untuk seisi keluarga selama 12 bulan.') }}
                @endif
            </p>

            @if ($onSale)
                <div class="relative mt-8 {{ $card }} text-left">
                    @if ($savingPercent)
                        <span class="absolute -top-4 right-5 rounded-full border-2 border-ink bg-kuning px-3 py-1 text-sm font-extrabold">{{ __('Jimat :percent%', ['percent' => $savingPercent]) }}</span>
                    @endif
                    <p class="text-sm font-extrabold tracking-wider text-oren uppercase">
                        {{ $offer->earlyBird() ? __('Early bird · 12 bulan') : __('Langganan 12 bulan') }}
                    </p>
                    <p class="mt-1 flex items-baseline gap-2">
                        <span class="font-display text-6xl leading-none font-extrabold text-ink">{{ Money::format($perMonth) }}</span>
                        <span class="text-lg font-bold text-ink/70">{{ __('sebulan') }}</span>
                    </p>
                    <p class="mt-4 inline-block rounded-2xl border-2 border-ink/15 bg-latar px-3 py-2 font-semibold">
                        {{ __('Dibayar sekali setahun:') }} <strong class="font-display text-xl">{{ Money::short($price) }}</strong>
                    </p>
                    @if ($yearAgainst)
                        <p class="mt-3 text-ink/75">
                            {{ $yearAgainst['label'] }}: <s class="text-magenta">{{ Money::format($yearAgainst['sen']) }}</s>.
                            <strong class="text-ink">{{ __('Jimat :amount.', ['amount' => Money::format($yearAgainst['sen'] - $price)]) }}</strong>
                        </p>
                    @endif

                    @if ($offer->earlyBird())
                        <x-slot-counter :limit="$offer->slotLimit()" :remaining="$offer->slotsRemaining()" class="mt-4" />
                    @endif

                    <a href="{{ $cta }}" class="btn-utama mt-5 w-full flex-col gap-0 py-3 text-lg">
                        <span class="whitespace-nowrap">{{ $offer->ctaLabel() }} &rarr;</span>
                        <span class="font-sans text-xs font-bold text-ink/70">{{ __('Seisi keluarga, sehingga 4 anak') }}</span>
                    </a>
                    <p class="mt-3 text-center text-sm text-ink/70">{{ __(':day sehari untuk seisi keluarga. Tiada caj automatik.', ['day' => Money::format($perDay)]) }}</p>
                </div>
                <x-monthly-option class="mt-4" />
            @else
                <p class="mt-8 rounded-2xl border-2 border-ink bg-white p-4 font-semibold">{{ __('Langganan Tahunan belum dijual buat masa ini. Sila kembali tidak lama lagi.') }}</p>
            @endif

            @if ($spotlight)
                <figure class="mt-8 {{ $card }} text-left">
                    <p class="text-kuning" aria-hidden="true">★★★★★</p>
                    <blockquote class="mt-2 font-display text-lg leading-snug font-bold">“{{ $spotlight->localQuote() }}”</blockquote>
                    <figcaption class="mt-2 text-sm text-ink/70">{{ $spotlight->author }}{{ $spotlight->detail ? ' · '.$spotlight->detail : '' }}</figcaption>
                </figure>
            @endif

            <ul class="mt-6 grid grid-cols-2 gap-3 text-left text-sm font-bold">
                @foreach ([
                    ['🛡️', __('Setiap buku disemak syariah')],
                    ['👨‍👩‍👧‍👦', __('Sehingga 4 anak, satu akaun')],
                    ['🚫', __('Tiada iklan, tiada video asing')],
                    ['🔒', __('Mod Anak berkunci dan laporan mingguan')],
                ] as [$icon, $text])
                    <li class="flex items-center gap-3 rounded-2xl border-2 border-ink/15 bg-white p-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-kuning text-xl" aria-hidden="true">{{ $icon }}</span>
                        {{ $text }}
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
    <div class="scallop text-lavender" aria-hidden="true"></div>

    <x-campaign-banner class="pt-10" />

    {{-- 2. The parents' worries --}}
    <section class="mx-auto max-w-xl px-4 py-14">
        <x-section-label>{{ __('Kenal situasi ni?') }}</x-section-label>
        <h2 class="mt-3 font-display text-3xl leading-tight font-extrabold text-plum">{{ __('Ibu bapa bukan tak nak bagi skrin. Kita cuma nak skrin yang bermanfaat.') }}</h2>
        <ul class="mt-6 space-y-3">
            @foreach ([
                ['😩', __('“Bagi telefon sekejap, sedar-sedar dah sejam tengok YouTube.”')],
                ['📚', __('“Nak cari bacaan Islamik yang anak betul-betul suka, susah. Yang ada pun cepat habis dibaca.”')],
                ['💸', __('“Beli buku satu-satu, mahal. Lepas dua kali baca, anak dah tak pandang.”')],
            ] as [$icon, $quote])
                <li class="flex items-start gap-3 {{ $card }} p-4">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-lavender text-xl" aria-hidden="true">{{ $icon }}</span>
                    <p class="text-lg">{{ $quote }}</p>
                </li>
            @endforeach
        </ul>
        <p class="mt-4 font-semibold text-ink/75">{{ __('Ini antara cerita yang paling kerap kami dengar daripada ibu bapa. Sebab itulah ASUH Digital wujud.') }}</p>
    </section>

    {{-- 3. Parents' own words (shown from two published testimonials) --}}
    @if ($testimonials->isNotEmpty())
        <section class="bg-lavender">
            <div class="mx-auto max-w-xl px-4 py-14">
                <x-section-label>{{ __('Dari keluarga ASUH') }}</x-section-label>
                <h2 class="mt-3 font-display text-3xl leading-tight font-extrabold text-plum">{{ __('Alhamdulillah, ini yang ibu bapa kongsi dengan kami.') }}</h2>
                <ul class="mt-6 space-y-4">
                    @foreach ($testimonials as $testimonial)
                        <li class="{{ $card }} p-4">
                            @if ($testimonial->imageUrl())
                                <img src="{{ $testimonial->imageUrl() }}" alt="{{ __('Mesej daripada :name', ['name' => $testimonial->author]) }}" loading="lazy" class="w-full rounded-2xl">
                            @endif
                            @if (filled($testimonial->localQuote()))
                                <p @class(['text-lg font-semibold', 'mt-3' => $testimonial->imageUrl()])>“{{ $testimonial->localQuote() }}”</p>
                            @endif
                            <p class="mt-2 text-sm text-ink/70">{{ $testimonial->author }}{{ $testimonial->detail ? ' · '.$testimonial->detail : '' }}</p>
                        </li>
                    @endforeach
                </ul>
                @if ($testimonials->contains(fn ($t) => $t->image_path))
                    <p class="mt-3 text-sm text-ink/70">{{ __('Mesej sebenar daripada ibu bapa. Nama dan nombor dikaburkan.') }}</p>
                @endif
            </div>
        </section>
        <div class="scallop text-lavender" aria-hidden="true"></div>
    @endif

    {{-- 4. Why ASUH is different --}}
    <section class="mx-auto max-w-xl px-4 py-14">
        <x-section-label>{{ __('Kenapa ASUH berbeza') }}</x-section-label>
        <h2 class="mt-3 font-display text-3xl leading-tight font-extrabold text-plum">{{ __('Skrin yang sama. Kesan yang berbeza.') }}</h2>
        <p class="mt-2 text-lg text-ink/80">{{ __('Di ASUH, setiap sentuhan anak membawa kepada membaca, bukan video seterusnya.') }}</p>
        <ol class="mt-6 space-y-3">
            @foreach ([
                [__('Anak pilih buku dalam Mod Anak'), __('Hanya buku ikut umurnya. Tiada harga, tiada iklan, tiada pautan keluar. Untuk keluar, perlu PIN ibu bapa.')],
                [__('Baca, kemudian jawab kuiz'), __('Selak macam buku sebenar. Lulus kuiz, dapat lencana. Lencana diberi kerana faham, bukan kerana selak halaman.')],
                [__('Ibu bapa terima laporan mingguan'), __('Minit membaca, buku yang dibaca dan lencana baharu setiap Isnin. Ibu bapa juga boleh janjikan ganjaran sendiri.')],
            ] as $i => [$title, $body])
                <li class="flex gap-4 {{ $card }} p-4">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-oren font-display text-lg font-extrabold text-ink">{{ $i + 1 }}</span>
                    <div>
                        <h3 class="font-display text-lg font-bold">{{ $title }}</h3>
                        <p class="mt-1 text-ink/80">{{ $body }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
        <p class="mt-4 rounded-3xl border-2 border-biru/30 bg-biru/5 p-5 font-semibold">
            {{ __('Setiap buku ASUH lulus semakan syariah sebelum diterbitkan. Anak boleh tekan apa sahaja di rak, semuanya sudah disemak.') }}
        </p>
    </section>

    {{-- 5. The books, by age --}}
    @if (collect($shelves)->flatten()->isNotEmpty())
        <section class="bg-lavender py-14">
            <div class="mx-auto max-w-xl px-4">
                <x-section-label>{{ __(':count e-book di ASUH Digital', ['count' => $bookCount]) }}</x-section-label>
                <h2 class="mt-3 font-display text-3xl leading-tight font-extrabold text-plum">{{ __('Apa anak baca, itulah yang anak ikut.') }}</h2>
                <p class="mt-2 text-lg text-ink/80">{{ __('Kisah nabi, akhlak, doa harian, komik dan sirah. Buku baharu setiap bulan.') }}</p>
            </div>
            @foreach ($shelves as $age => $shelf)
                @if ($shelf->isNotEmpty())
                    <h3 class="mx-auto mt-8 max-w-xl px-4 font-display text-xl font-bold">{{ __('Untuk umur :age', ['age' => $age]) }}</h3>
                    <div class="marquee mt-3 overflow-hidden">
                        <div @class(['marquee-track flex gap-4', 'marquee-reverse' => $loop->even])>
                            @foreach ([false, true] as $copy)
                                @foreach ($shelf as $book)
                                    <div class="w-28 shrink-0 sm:w-32" @if ($copy) aria-hidden="true" @endif>
                                        <x-book-cover :book="$book" />
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
            @if ($catalogOpen)
                <p class="mx-auto mt-6 max-w-xl px-4"><a href="{{ route('catalog.index') }}" class="pautan font-bold">{{ __('Lihat semua :count buku', ['count' => $bookCount]) }} &rarr;</a></p>
            @endif
        </section>
        <div class="scallop text-lavender" aria-hidden="true"></div>
    @endif

    {{-- 6. Compare --}}
    <section class="mx-auto max-w-xl px-4 py-14">
        <x-section-label>{{ $showMaths ? __('Matematik mudah') : __('Bandingkan sendiri') }}</x-section-label>
        <h2 class="mt-3 font-display text-3xl leading-tight font-extrabold text-plum">{{ __('Semuanya skrin. Tapi sangat berbeza.') }}</h2>
        <div class="mt-6 overflow-x-auto rounded-3xl border-2 border-ink bg-white shadow-[0_4px_0_var(--color-ink)]">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-ink/10">
                        <th class="p-3 text-left"><span class="sr-only">{{ __('Perkara') }}</span></th>
                        <th class="p-3 text-center font-bold">{{ __('Beli buku satu-satu') }}</th>
                        <th class="p-3 text-center font-bold">{{ __('Video percuma') }}</th>
                        <th class="bg-kuning p-3 text-center font-display text-base font-extrabold">ASUH</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b-2 border-ink/10">
                        <th class="p-3 text-left font-semibold">{{ __('Kos setahun') }}</th>
                        <td class="p-3 text-center">{{ $showMaths ? Money::short($singleTotal).'+' : __('Bayar setiap buku') }}</td>
                        <td class="p-3 text-center">{{ __('Percuma, ada iklan') }}</td>
                        <td class="bg-kuning/30 p-3 text-center font-display text-lg font-extrabold">{{ $onSale ? Money::short($price) : '-' }}</td>
                    </tr>
                    @foreach ([
                        [__('Disemak syariah'), null, false],
                        [__('Tiada iklan'), true, false],
                        [__('Buku baharu termasuk'), false, null],
                        [__('Kuiz dan lencana'), false, false],
                        [__('Laporan untuk ibu bapa'), false, false],
                    ] as [$row, $books, $videos])
                        <tr class="border-b-2 border-ink/10 last:border-0">
                            <th class="p-3 text-left font-semibold">{{ $row }}</th>
                            @foreach ([$books, $videos] as $value)
                                <td class="p-3 text-center">
                                    @if ($value === true) <span class="text-biru" aria-label="{{ __('Ya') }}">✓</span>
                                    @elseif ($value === false) <span class="text-ink/40" aria-label="{{ __('Tidak') }}">✕</span>
                                    @else <span class="text-xs text-ink/60">{{ __('Bergantung') }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="bg-kuning/30 p-3 text-center text-lg font-extrabold text-biru" aria-label="{{ __('Ya') }}">✓</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($showMaths)
            <p class="mt-4 text-lg">
                {{ __('Beli semua :count buku satu-satu: :total. Langganan Tahunan: :price.', ['count' => $bookCount, 'total' => Money::short($singleTotal), 'price' => Money::short($price)]) }}
                <strong class="rounded-full bg-ink px-3 py-0.5 text-kuning">{{ __('Anda jimat :percent%', ['percent' => $mathsPercent]) }}</strong>
            </p>
        @endif
    </section>

    {{-- 7. Plans and what a year costs --}}
    @if ($onSale && ($monthly || $yearAgainst))
        <section class="bg-lavender">
            <div class="mx-auto max-w-xl px-4 py-14">
                <x-section-label>{{ __('Semua pelan') }}</x-section-label>
                <h2 class="mt-3 font-display text-3xl leading-tight font-extrabold text-plum">{{ __('Satu harga untuk seisi keluarga.') }}</h2>

                @if ($monthly)
                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <a href="{{ route('purchase.pass', ['tempoh' => 'bulanan']) }}" class="{{ $card }} block p-4 text-center hover:bg-latar">
                            <p class="font-bold">{{ __('1 bulan') }}</p>
                            <p class="mt-2 font-display text-3xl font-extrabold">{{ Money::format($monthly->price_sen) }}</p>
                            <p class="text-sm text-ink/70">{{ __('bulan ke bulan') }}</p>
                            <span class="btn-gelap mt-3 w-full">{{ __('Pilih') }}</span>
                        </a>
                        <a href="{{ $cta }}" class="relative block rounded-3xl border-2 border-ink bg-kuning p-4 text-center shadow-[0_4px_0_var(--color-ink)]">
                            @if ($monthlySavingPercent)
                                <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full border-2 border-ink bg-white px-2 text-xs font-extrabold whitespace-nowrap">{{ __('Jimat :percent%', ['percent' => $monthlySavingPercent]) }}</span>
                            @endif
                            <p class="font-bold">{{ __('12 bulan') }}</p>
                            <p class="mt-2 font-display text-3xl font-extrabold">{{ Money::format($perMonth) }}</p>
                            <p class="text-sm text-ink/70">{{ __(':price / tahun', ['price' => Money::short($price)]) }}</p>
                            <span class="btn-gelap mt-3 w-full">{{ __('Pilih') }}</span>
                        </a>
                    </div>
                @endif

                @if ($yearAgainst)
                    <div class="mt-6 {{ $card }}">
                        @foreach ([[$yearAgainst['label'], $yearAgainst['sen'], false], [$offer->earlyBird() ? __('Early bird, 12 bulan') : __('Langganan Tahunan'), $price, true]] as [$label, $sen, $ours])
                            <div @class(['mt-4' => $ours])>
                                <p class="font-bold">{{ $label }}</p>
                                <div class="mt-1 flex items-center gap-3">
                                    <div class="h-4 flex-1 rounded-full bg-latar">
                                        <div @class(['h-4 rounded-full', 'bg-oren' => $ours, 'bg-ink/25' => ! $ours]) style="width: {{ max(8, round($sen / $yearAgainst['sen'] * 100)) }}%"></div>
                                    </div>
                                    <p @class(['w-24 text-right font-display text-lg font-extrabold', 'text-oren' => $ours])>{{ Money::format($sen) }}</p>
                                </div>
                            </div>
                        @endforeach
                        <p class="mt-5 font-display text-xl font-extrabold">
                            {{ __('Jimat :amount.', ['amount' => Money::format($yearAgainst['sen'] - $price)]) }}
                            @if ($monthly && intdiv($yearAgainst['sen'] - $price, $monthly->price_sen) >= 1)
                                {{ __('Macam dapat :months bulan percuma.', ['months' => intdiv($yearAgainst['sen'] - $price, $monthly->price_sen)]) }}
                            @endif
                        </p>
                        @if ($monthly)
                            <p class="mt-1 text-sm text-ink/70">{{ __('Berbanding 12 × :price untuk pelan bulanan.', ['price' => Money::format($monthly->price_sen)]) }}</p>
                        @endif
                        <a href="{{ $cta }}" class="btn-utama mt-5 w-full flex-col gap-0 py-3 text-lg">
                            <span class="whitespace-nowrap">{{ $offer->ctaLabel() }} &rarr;</span>
                            <span class="font-sans text-xs font-bold text-ink/70">{{ Money::short($price) }} · {{ __('Cuma :month sebulan', ['month' => Money::format($perMonth)]) }}</span>
                        </a>
                    </div>
                @endif
            </div>
        </section>
        <div class="scallop text-lavender" aria-hidden="true"></div>
    @endif

    {{-- 8. Start in three steps --}}
    <section class="mx-auto max-w-xl px-4 py-14">
        <x-section-label>{{ __('Mula dalam 3 langkah') }}</x-section-label>
        <h2 class="mt-3 font-display text-3xl leading-tight font-extrabold text-plum">{{ __('Satu akaun. Semua anak. Semua peranti.') }}</h2>
        <ol class="mt-6 space-y-3">
            @foreach ([
                [__('Langgan di sini'), __('FPX, kad, e-wallet atau pindahan bank. Akaun ibu bapa dicipta semasa bayaran; ingat e-mel yang digunakan.')],
                [__('Tambah profil anak'), __('Nama panggilan dan umur sahaja, sehingga 4 anak. Tetapkan PIN ibu bapa untuk keluar dari Mod Anak.')],
                [__('Tekan Mod Anak, mula membaca'), __('Di telefon, tablet atau komputer. Pasang app ASUH di skrin utama supaya anak terus jumpa raknya.')],
            ] as $i => [$title, $body])
                <li class="flex gap-4 {{ $card }} p-4">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-plum font-display text-lg font-extrabold text-white">{{ $i + 1 }}</span>
                    <div>
                        <h3 class="font-display text-lg font-bold">{{ $title }}</h3>
                        <p class="mt-1 text-ink/80">{{ $body }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
        <p class="mt-4 font-display text-xl font-bold">
            {{ $passStartRule === PassStartRule::OnLaunch ? __('Tiga langkah sahaja. Anak boleh mula membaca sebaik platform dilancarkan.') : __('Tiga langkah sahaja. Anak boleh mula membaca malam ini.') }}
        </p>
    </section>

    {{-- 9. Honestly: who it is for --}}
    <section class="bg-lavender">
        <div class="mx-auto max-w-xl px-4 py-14">
            <x-section-label>{{ __('Jujur cakap') }}</x-section-label>
            <h2 class="mt-3 font-display text-3xl leading-tight font-extrabold text-plum">{{ __('ASUH Digital bukan untuk semua keluarga.') }}</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="{{ $card }}">
                    <h3 class="font-display text-lg font-bold text-biru">{{ __('Sesuai kalau anda') }}</h3>
                    <ul class="mt-3 space-y-2">
                        @foreach ([__('Nak masa skrin anak jadi masa membaca'), __('Nak bahan Islamik yang sudah disemak'), __('Nak satu langganan untuk semua adik-beradik')] as $line)
                            <li class="flex gap-2"><span class="text-biru" aria-hidden="true">✓</span>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="{{ $card }}">
                    <h3 class="font-display text-lg font-bold text-magenta">{{ __('Tak sesuai kalau anda') }}</h3>
                    <ul class="mt-3 space-y-2">
                        @foreach ([__('Nak skrin ganti tempat ibu bapa sepenuhnya'), __('Cari kartun untuk ditonton sahaja'), __('Mahu buku fizikal untuk dipegang')] as $line)
                            <li class="flex gap-2"><span class="text-magenta" aria-hidden="true">✕</span>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>
    <div class="scallop text-lavender" aria-hidden="true"></div>

    {{-- 10. Questions --}}
    <section class="mx-auto max-w-xl px-4 py-14">
        <x-section-label>{{ __('Soalan ibu bapa') }}</x-section-label>
        <h2 class="mt-3 font-display text-3xl leading-tight font-extrabold text-plum">{{ __('Soalan lazim') }}</h2>
        <div class="mt-6 space-y-3">
            @if ($monthly)
                <x-faq-item :question="__('Tahunan atau bulanan, mana satu?')">
                    {{ __('Tahunan paling jimat: :price setahun, iaitu :month sebulan. Bayar bulanan selama 12 bulan jadi :total. Nak cuba dulu? Ambil 1 bulan (:monthly), dan tukar ke tahunan bila-bila masa.', ['price' => Money::short($price), 'month' => Money::format($perMonth), 'total' => Money::format($monthly->price_sen * 12), 'monthly' => Money::format($monthly->price_sen)]) }}
                </x-faq-item>
            @endif
            @foreach ($faq as $item)
                <x-faq-item :question="$item['question']">{{ $item['answer'] }}</x-faq-item>
            @endforeach
            <x-faq-item :question="__('Bila Langganan Tahunan saya bermula?')">
                @if ($passStartRule === PassStartRule::OnLaunch)
                    {{ __('Tempoh 12 bulan bermula pada tarikh platform ASUH Digital dilancarkan, jadi anda tidak kehilangan sebarang bulan.') }}
                @else
                    {{ __('Tempoh 12 bulan bermula sebaik sahaja bayaran anda disahkan.') }}
                @endif
            </x-faq-item>
            <x-faq-item :question="__('Boleh saya minta bayaran balik?')">{{ $refundPolicy }}</x-faq-item>
        </div>
    </section>

    {{-- 11. Last call --}}
    @if ($onSale)
        <section class="bg-plum text-white">
            <div class="mx-auto max-w-xl px-4 py-14 text-center">
                <h2 class="font-display text-4xl leading-tight font-extrabold text-kuning">{{ __('Tukar masa skrin jadi masa membaca.') }}</h2>
                <p class="mt-3 text-lg text-white/85">{{ __('Malam ini, insya-Allah, anak sudah boleh membaca buku ASUH di rumah.') }}</p>
                <a href="{{ $cta }}" class="btn-utama mt-6 w-full flex-col gap-0 py-3 text-lg">
                    <span class="whitespace-nowrap">{{ $offer->ctaLabel() }} &rarr;</span>
                    <span class="font-sans text-xs font-bold text-ink/70">{{ __('Serendah :month sebulan', ['month' => Money::format($perMonth)]) }}</span>
                </a>
                <div class="mt-8 space-y-2 text-left text-white/85">
                    <p><strong>P.S.</strong> {{ __('Tiada caj automatik. Bayar sekali untuk 12 bulan, dan kami ingatkan anda sebelum tamat.') }}</p>
                    <p><strong>P.P.S.</strong> {{ __(':price setahun ialah :month sebulan untuk seisi keluarga.', ['price' => Money::short($price), 'month' => Money::format($perMonth)]) }}</p>
                    <p><strong>P.P.P.S.</strong> {{ __('Satu langganan untuk sehingga 4 anak, di telefon, tablet dan komputer.') }}</p>
                </div>
            </div>
        </section>

        {{-- Sticky call to action once the hero has scrolled away --}}
        <div x-data="{ show: false }" x-cloak x-show="show" x-transition.opacity
            @scroll.window.throttle.100ms="show = window.scrollY > 900 && (window.innerHeight + window.scrollY) < document.body.scrollHeight - 700"
            @class(['fixed bottom-[calc(4.75rem+env(safe-area-inset-bottom))] left-4 z-30 lg:right-4 lg:bottom-6 lg:mx-auto lg:max-w-md', 'right-20' => $whatsapp, 'right-4' => ! $whatsapp])>
            <a href="{{ $cta }}" class="btn-utama w-full flex-col gap-0 py-2 text-lg">
                <span>{{ $offer->ctaLabel() }} &rarr;</span>
                <span class="font-sans text-xs font-bold text-ink/70">{{ __('Serendah :month sebulan', ['month' => Money::format($perMonth)]) }}</span>
            </a>
        </div>
    @endif
</x-layouts.app>
