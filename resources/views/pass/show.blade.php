@php
    use App\Enums\PassStartRule;
    use App\Support\Money;

    $onSale = $offer->available();
    $price = $offer->priceSen();
    $compareAt = $offer->compareAtSen();
    $bookCount = $stats['books'];
    // "Matematik mudah" only when buying the priced books one by one really costs more.
    $singleTotal = $stats['single_total_sen'];
    $showMaths = $onSale && $singleTotal > $price;
    $savingPercent = $showMaths ? (int) floor(100 - ($price / $singleTotal * 100)) : null;
    $card = 'rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]';
@endphp

<x-layouts.app :title="__('Langganan Tahunan')">
    {{-- Hero --}}
    <section class="bg-plum text-white">
        <div class="mx-auto max-w-3xl px-4 py-14 text-center">
            @if ($compareAt)
                <p class="inline-block rounded-full border-2 border-ink bg-oren px-3 py-1 text-sm font-extrabold text-ink">
                    🔥 {{ __('Harga early bird · jimat :amount', ['amount' => Money::short($compareAt - $price)]) }}
                </p>
            @endif
            <h1 class="mt-4 font-display text-4xl leading-tight font-extrabold text-kuning sm:text-5xl">{{ __('Langganan Tahunan ASUH Digital') }}</h1>
            <p class="mt-3 text-lg text-white/90">
                @if ($bookCount > 0)
                    {{ __('Semua :count e-book Islamik untuk anak, ditambah setiap buku baharu, untuk seisi keluarga selama 12 bulan.', ['count' => $bookCount]) }}
                @else
                    {{ __('Semua e-book Islamik ASUH untuk anak 6–13 tahun, untuk seisi keluarga selama 12 bulan.') }}
                @endif
            </p>

            @if ($onSale)
                <p class="mt-6 font-display text-6xl font-extrabold">
                    {{ Money::short($price) }}
                    @if ($compareAt)
                        <span class="text-3xl text-white/60 line-through">{{ Money::short($compareAt) }}</span>
                    @endif
                </p>
                @if ($bookCount > 1)
                    <p class="mt-1 font-semibold text-white/80">{{ __('Kira-kira :book sebuku, atau :day sehari.', ['book' => Money::format(intdiv($price, $bookCount)), 'day' => Money::format(intdiv($price, 365))]) }}</p>
                @endif
            @endif

            <ul class="mt-6 flex flex-wrap justify-center gap-x-6 gap-y-2 font-bold">
                <li>✓ {{ __('Sekali bayar') }}</li>
                <li>✓ {{ __('Akses 12 bulan') }}</li>
                <li>✓ {{ __('Tiada caj automatik') }}</li>
            </ul>

            @if ($offer->earlyBird())
                <x-slot-counter :limit="$offer->slotLimit()" :remaining="$offer->slotsRemaining()" class="mx-auto mt-6 max-w-md rounded-2xl bg-white p-4 text-left text-ink" />
            @endif

            @if ($onSale)
                <a href="{{ $offer->checkoutUrl() }}" class="btn-utama mt-8 text-lg">{{ $offer->ctaLabel() }} &rarr;</a>
                <x-monthly-option dark class="mt-4" />
            @else
                <p class="mt-8 rounded-xl bg-white/10 p-4 font-semibold">{{ __('Langganan Tahunan belum dijual buat masa ini. Sila kembali tidak lama lagi.') }}</p>
            @endif
        </div>
    </section>

    {{-- Why a pass --}}
    <section class="mx-auto max-w-3xl px-4 py-14">
        <p class="text-sm font-extrabold tracking-wide text-oren uppercase">{{ __('Kenapa Langganan Tahunan?') }}</p>
        <h2 class="mt-1 font-display text-3xl font-extrabold text-plum">{{ __('Biar kami berterus terang…') }}</h2>
        <div class="mt-4 space-y-4 text-lg text-ink/85">
            <p>{{ __('Kami boleh sahaja jual buku satu-satu. Tetapi anak-anak tidak membaca satu buku sahaja. Hari ini kisah nabi, esok komik akhlak, lusa doa harian. Minat membaca tumbuh apabila ada banyak pilihan di depan mata.') }}</p>
            <p>{{ __('Sebab itulah wujudnya Langganan Tahunan: satu bayaran, dan seisi keluarga boleh membaca semua buku ASUH Digital sepanjang tahun, termasuk buku baharu yang kami terbitkan selepas ini.') }}</p>
        </div>
    </section>

    {{-- Simple maths --}}
    @if ($showMaths)
        <section class="bg-lavender">
            <div class="mx-auto max-w-4xl px-4 py-14">
                <p class="text-center text-sm font-extrabold tracking-wide text-oren uppercase">{{ __('Matematik mudah') }}</p>
                <h2 class="mt-1 text-center font-display text-3xl font-extrabold text-plum">{{ __('Beli satu-satu atau Langganan Tahunan?') }}</h2>
                <div class="mt-8 grid items-center gap-4 md:grid-cols-[1fr_auto_1fr]">
                    <div class="{{ $card }}">
                        <p class="font-display text-lg font-bold">{{ __('💸 Beli cara biasa') }}</p>
                        <dl class="mt-3 space-y-2">
                            <div class="flex justify-between gap-4"><dt>{{ __('Beli buku satu-satu') }}</dt><dd class="font-bold">{{ Money::short($singleTotal) }}</dd></div>
                            <div class="flex justify-between gap-4"><dt>{{ __('Buku baharu akan datang') }}</dt><dd class="font-bold">{{ __('+ bayar lagi') }}</dd></div>
                            <div class="flex justify-between gap-4 border-t-2 border-ink/10 pt-2"><dt class="font-bold">{{ __('Kos anda') }}</dt><dd class="font-display text-2xl font-extrabold text-magenta">{{ Money::short($singleTotal) }}+</dd></div>
                        </dl>
                    </div>
                    <p class="text-center font-display text-3xl font-extrabold text-plum" aria-hidden="true">&rarr;</p>
                    <div class="rounded-3xl border-2 border-ink bg-kuning p-6 shadow-[0_4px_0_var(--color-ink)]">
                        <p class="font-display text-lg font-bold">{{ __('✓ Langganan Tahunan · 12 bulan') }}</p>
                        <p class="mt-3 font-display text-4xl font-extrabold">
                            {{ Money::short($price) }}
                            @if ($compareAt) <span class="text-xl text-ink/60 line-through">{{ Money::short($compareAt) }}</span> @endif
                        </p>
                        <p class="font-semibold">{{ __('sekali bayar, semua buku') }}</p>
                        <p class="mt-3 inline-block rounded-full bg-ink px-3 py-1 font-extrabold text-kuning">{{ __('Anda jimat :percent%', ['percent' => $savingPercent]) }}</p>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Value stack --}}
    <section class="mx-auto max-w-4xl px-4 py-14">
        <p class="text-center text-sm font-extrabold tracking-wide text-oren uppercase">{{ __('📦 Apa yang anda dapat') }}</p>
        <h2 class="mt-1 text-center font-display text-3xl font-extrabold text-plum">{{ __('Bukan sekadar e-book. Rak buku seisi keluarga.') }}</h2>
        <ol class="mt-8 space-y-4">
            @foreach ([
                ['🔓', $bookCount > 0 ? __('Akses semua :count e-book sedia ada', ['count' => $bookCount]) : __('Akses semua e-book ASUH Digital'), __('Komik, kisah nabi, akhlak, doa harian dan sirah. Selak macam buku sebenar di telefon, tablet atau komputer.'), $singleTotal > 0 ? __('Bernilai :amount', ['amount' => Money::short($singleTotal)]) : __('TERMASUK')],
                ['🔄', __('Buku baharu automatik termasuk'), __('Setiap buku baharu yang kami terbitkan sepanjang tempoh langganan terus terbuka untuk anda.'), __('PERCUMA')],
                ['👨‍👩‍👧', __('Seisi keluarga'), __('Satu langganan untuk sehingga 4 profil anak dalam satu akaun ibu bapa.'), __('TERMASUK')],
                ['🛡️', __('Disemak syariah'), __('Setiap buku mesti lulus semakan syariah sebelum diterbitkan.'), __('TERMASUK')],
                ['⚡', __('Akses serta-merta'), __('Bayar dengan FPX, kad atau e-wallet dan buku terus terbuka. Pindahan bank juga diterima.'), __('TERMASUK')],
                ['🤝', __('Tiada caj automatik'), __('Bayar sekali untuk 12 bulan. Kami ingatkan anda sebelum tamat, dan anda yang tentukan sama ada mahu sambung.'), __('TERMASUK')],
            ] as $i => [$icon, $title, $body, $tag])
                <li class="{{ $card }} flex gap-4">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-plum font-display font-extrabold text-white">{{ $i + 1 }}</span>
                    <div class="flex-1">
                        <h3 class="font-display text-lg font-bold">{{ $icon }} {{ $title }}</h3>
                        <p class="mt-1 text-ink/80">{{ $body }}</p>
                    </div>
                    <span class="hidden h-fit shrink-0 rounded-full bg-biru/10 px-3 py-1 text-xs font-extrabold text-biru sm:inline">{{ $tag }}</span>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- How it works --}}
    <section class="bg-lavender">
        <div class="mx-auto max-w-4xl px-4 py-14">
            <h2 class="text-center font-display text-3xl font-extrabold text-plum">{{ __('Bagaimana ia berfungsi?') }}</h2>
            <ol class="mt-8 grid gap-4 md:grid-cols-3">
                @foreach ([
                    [__('Pilih & bayar'), __('FPX, kad debit/kredit, e-wallet atau pindahan bank. Akaun dicipta semasa pembayaran.')],
                    [__('Baca sepuasnya'), __('Semua buku terbuka sepanjang 12 bulan, di mana-mana peranti, untuk seisi keluarga.')],
                    [__('Sambung bila perlu'), __('Kami hantar peringatan sebelum tamat. Tiada potongan automatik.')],
                ] as $i => [$title, $body])
                    <li class="{{ $card }}">
                        <p class="font-display text-3xl font-extrabold text-magenta">{{ $i + 1 }}.</p>
                        <h3 class="mt-1 font-display text-xl font-bold text-plum">{{ $title }}</h3>
                        <p class="mt-1 text-ink/80">{{ $body }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Books included --}}
    @if ($books->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 py-14" aria-labelledby="included-heading">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <h2 id="included-heading" class="font-display text-3xl font-extrabold text-plum">{{ __('Buku yang termasuk') }}</h2>
                <a href="{{ route('catalog.index') }}" class="pautan text-sm font-bold">{{ __('Lihat semua :count buku', ['count' => $bookCount]) }} &rarr;</a>
            </div>
            <ul class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-4 lg:grid-cols-6">
                @foreach ($books as $book)
                    <li><x-book-card :book="$book" /></li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- FAQ --}}
    <section class="mx-auto max-w-3xl px-4 py-14">
        <h2 class="text-center font-display text-3xl font-extrabold text-plum">{{ __('Soalan lazim') }}</h2>
        <div class="mt-8 space-y-3">
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

    @if ($onSale)
        <section class="mx-auto max-w-6xl px-4 pb-4">
            <div class="rounded-3xl border-2 border-ink bg-kuning p-8 text-center shadow-[0_6px_0_var(--color-ink)]">
                <h2 class="font-display text-3xl font-extrabold">{{ __('Satu bayaran. Seisi keluarga. Setahun membaca.') }}</h2>
                <p class="mt-2 font-display text-4xl font-extrabold">{{ Money::short($price) }} @if ($compareAt) <span class="text-xl text-ink/60 line-through">{{ Money::short($compareAt) }}</span> @endif</p>
                <a href="{{ $offer->checkoutUrl() }}" class="btn-gelap mt-6 text-lg">{{ $offer->ctaLabel() }} &rarr;</a>
            </div>
        </section>
    @endif
</x-layouts.app>
