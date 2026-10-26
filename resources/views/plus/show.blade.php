@php
    use App\Support\Money;

    $price = $plan->price_sen;
    $thumb = fn ($video) => $video->thumbnail_path ? Storage::disk('public')->url($video->thumbnail_path) : null;
    $card = 'rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]';
@endphp

<x-layouts.app :title="__('Langganan Plus')">
    {{-- Hero --}}
    <section class="bg-plum text-white">
        <div class="mx-auto max-w-3xl px-4 py-14 text-center">
            <p class="inline-block rounded-full border-2 border-ink bg-oren px-3 py-1 text-sm font-extrabold text-ink">{{ __('Baharu · e-book + video animasi') }}</p>
            <h1 class="mt-4 font-display text-4xl leading-tight font-extrabold text-kuning sm:text-5xl">{{ __('Langganan Plus ASUH Digital') }}</h1>
            <p class="mt-3 text-lg text-white/90">
                {{ __('Semua e-book ASUH, ditambah semua video animasi, untuk seisi keluarga selama 12 bulan. Anak membaca dahulu, kemudian menonton kisahnya bergerak dan bersuara.') }}
            </p>

            <p class="mt-6 font-display text-6xl font-extrabold">{{ Money::short($price) }}</p>
            <p class="mt-1 font-semibold text-white/80">{{ __('Kira-kira :month sebulan, atau :day sehari.', ['month' => Money::format(intdiv($price, 12)), 'day' => Money::format(intdiv($price, 365))]) }}</p>

            <ul class="mt-6 flex flex-wrap justify-center gap-x-6 gap-y-2 font-bold">
                <li>✓ {{ __('Sekali bayar') }}</li>
                <li>✓ {{ __('Akses 12 bulan') }}</li>
                <li>✓ {{ __('Tiada caj automatik') }}</li>
            </ul>

            <a href="{{ $cta['url'] }}" class="btn-utama mt-8 text-lg">{{ $cta['label'] }} &rarr;</a>
            @if ($upgradePrice !== null)
                <p class="mt-3 text-sm font-semibold text-white/85">{{ __('Anda sudah ada Langganan Tahunan: bayar beza harga untuk baki hari sahaja.') }}</p>
            @elseif (! $subscribed)
                <x-monthly-option product="plus" dark class="mt-4" />
            @endif
        </div>
    </section>

    <x-campaign-banner class="pt-10" />

    {{-- Why Plus --}}
    <section class="mx-auto max-w-3xl px-4 py-14">
        <p class="text-sm font-extrabold tracking-wide text-oren uppercase">{{ __('Kenapa Langganan Plus?') }}</p>
        <h2 class="mt-1 font-display text-3xl font-extrabold text-plum">{{ __('Anak memang suka menonton. Biar yang ditonton itu ASUH.') }}</h2>
        <div class="mt-4 space-y-4 text-lg text-ink/85">
            <p>{{ __('Anak-anak hari ini membesar dengan skrin. Kita tidak boleh menghalang mereka menonton, tetapi kita boleh memilih apa yang mereka tonton: animasi pendek daripada buku ASUH yang disemak syariah, tanpa iklan dan tanpa video lain yang muncul selepasnya.') }}</p>
            <p>{{ __('Dan menonton tidak menggantikan membaca. Dengan suis "Baca dulu, tonton kemudian", video sesebuah buku hanya dibuka selepas anak membaca bukunya. Membaca menjadi pintu, animasi menjadi ganjaran.') }}</p>
        </div>
    </section>

    {{-- Simple maths: what Plus adds to the yearly subscription --}}
    @if ($extraSen)
        <section class="bg-lavender">
            <div class="mx-auto max-w-4xl px-4 py-14">
                <p class="text-center text-sm font-extrabold tracking-wide text-oren uppercase">{{ __('Matematik mudah') }}</p>
                <h2 class="mt-1 text-center font-display text-3xl font-extrabold text-plum">{{ __('Langganan Tahunan atau Langganan Plus?') }}</h2>
                <div class="mt-8 grid items-center gap-4 md:grid-cols-[1fr_auto_1fr]">
                    <div class="{{ $card }}">
                        <p class="font-display text-lg font-bold">{{ __('📚 Langganan Tahunan') }}</p>
                        <p class="mt-3 font-display text-4xl font-extrabold">{{ Money::short($yearly->price_sen) }}</p>
                        <p class="font-semibold">{{ __('Semua e-book sahaja') }}</p>
                    </div>
                    <p class="text-center font-display text-3xl font-extrabold text-plum" aria-hidden="true">+</p>
                    <div class="rounded-3xl border-2 border-ink bg-kuning p-6 shadow-[0_4px_0_var(--color-ink)]">
                        <p class="font-display text-lg font-bold">{{ __('🎬 Langganan Plus') }}</p>
                        <p class="mt-3 font-display text-4xl font-extrabold">{{ Money::short($price) }}</p>
                        <p class="font-semibold">{{ __('Semua e-book dan semua video animasi') }}</p>
                        <p class="mt-3 inline-block rounded-full bg-ink px-3 py-1 font-extrabold text-kuning">{{ __('Hanya :extra lagi setahun, :month sebulan', ['extra' => Money::short($extraSen), 'month' => Money::format(intdiv($extraSen, 12))]) }}</p>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- A few of the videos --}}
    @if ($videos->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 py-14" aria-labelledby="videos-heading">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <h2 id="videos-heading" class="font-display text-3xl font-extrabold text-plum">{{ __('Video animasi yang termasuk') }}</h2>
                <a href="{{ route('videos.index') }}" class="pautan text-sm font-bold">{{ __('Lihat semua :count video', ['count' => $videoCount]) }} &rarr;</a>
            </div>
            <ul class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($videos as $video)
                    {{-- Three on a phone, six on bigger screens. --}}
                    <li @class(['min-w-0', 'hidden sm:block' => $loop->index >= 3])>
                        @if ($video->is_free_sample)
                            <x-showcase-video :embed="$showcase->embed($video)" :title="$video->title" :thumb="$thumb($video)" :duration="$video->durationLabel()" :end-cta="['text' => __('Suka? Tonton semua animasi ASUH dengan Langganan Plus.'), ...$cta]" free />
                        @else
                            <x-showcase-video :title="$video->title" :thumb="$thumb($video)" :duration="$video->durationLabel()" />
                        @endif
                        <h3 class="mt-3 font-display text-lg leading-tight font-bold">{{ $video->title }}</h3>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Value stack --}}
    <section @class(['bg-lavender' => ! $extraSen])>
        <div class="mx-auto max-w-4xl px-4 py-14">
            <p class="text-center text-sm font-extrabold tracking-wide text-oren uppercase">{{ __('📦 Apa yang anda dapat') }}</p>
            <h2 class="mt-1 text-center font-display text-3xl font-extrabold text-plum">{{ __('Baca dan tonton, dengan kawalan ibu bapa.') }}</h2>
            <ol class="mt-8 space-y-4">
                @foreach ([
                    ['🎬', $videoCount > 0 ? __('Semua :count video animasi', ['count' => $videoCount]) : __('Semua video animasi ASUH'), __('Kisah daripada buku ASUH yang bergerak dan bersuara, dan video baharu sepanjang tempoh langganan.')],
                    ['📚', $bookCount > 0 ? __('Semua :count e-book', ['count' => $bookCount]) : __('Semua e-book ASUH Digital'), __('Semua yang ada dalam Langganan Tahunan: komik, kisah nabi, akhlak, doa harian dan sirah.')],
                    ['📖', __('Baca dulu, tonton kemudian'), __('Suis ibu bapa: video sesebuah buku dibuka selepas anak membaca bukunya.')],
                    ['⏱️', __('Had tonton harian'), __('Ibu bapa tetapkan berapa minit sehari setiap anak boleh menonton.')],
                    ['🛡️', __('Tiada iklan, tiada pautan keluar'), __('Video dimainkan dalam Mod Anak dengan pemain ASUH sendiri, bukan di YouTube.')],
                    ['👨‍👩‍👧', __('Seisi keluarga'), __('Satu langganan untuk sehingga 4 profil anak dalam satu akaun ibu bapa.')],
                ] as $i => [$icon, $title, $body])
                    <li class="{{ $card }} flex gap-4">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-plum font-display font-extrabold text-white">{{ $i + 1 }}</span>
                        <div class="flex-1">
                            <h3 class="font-display text-lg font-bold">{{ $icon }} {{ $title }}</h3>
                            <p class="mt-1 text-ink/80">{{ $body }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- How it works --}}
    <section @class(['bg-lavender' => (bool) $extraSen])>
        <div class="mx-auto max-w-4xl px-4 py-14">
            <h2 class="text-center font-display text-3xl font-extrabold text-plum">{{ __('Bagaimana ia berfungsi?') }}</h2>
            <ol class="mt-8 grid gap-4 md:grid-cols-3">
                @foreach ([
                    [__('Pilih & bayar'), __('FPX, kad, e-wallet atau pindahan bank. Sudah ada Langganan Tahunan? Naik taraf dan bayar beza harga sahaja.')],
                    [__('Masuk Mod Anak'), __('Anak membaca dan menonton dalam Mod Anak, tanpa harga atau butang beli. Keluar hanya dengan PIN ibu bapa.')],
                    [__('Pantau setiap minggu'), __('Laporan mingguan memaparkan minit membaca dan menonton setiap anak.')],
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

    {{-- FAQ --}}
    <section class="mx-auto max-w-3xl px-4 py-14">
        <h2 class="text-center font-display text-3xl font-extrabold text-plum">{{ __('Soalan lazim') }}</h2>
        <div class="mt-8 space-y-3">
            <x-faq-item :question="__('Apa beza Langganan Tahunan dan Langganan Plus?')">
                {{ __('Langganan Tahunan membuka semua e-book. Langganan Plus membuka semua e-book dan semua video animasi ASUH.') }}
            </x-faq-item>
            <x-faq-item :question="__('Saya sudah ada Langganan Tahunan. Perlu bayar penuh?')">
                {{ __('Tidak. Anda boleh naik taraf dan membayar beza harga untuk baki hari langganan anda sahaja. Tarikh tamat kekal sama.') }}
            </x-faq-item>
            <x-faq-item :question="__('Di mana anak menonton video?')">
                {{ __('Dalam Mod Anak di ASUH Digital, di telefon, tablet atau komputer. Video tidak dibuka di YouTube, jadi tiada iklan atau cadangan video lain.') }}
            </x-faq-item>
            <x-faq-item :question="__('Boleh saya hadkan masa menonton?')">
                {{ __('Boleh. Tetapkan had minit sehari bagi setiap anak, dan hidupkan "Baca dulu, tonton kemudian" di profil anak.') }}
            </x-faq-item>
            <x-faq-item :question="__('Boleh saya minta bayaran balik?')">{{ $refundPolicy }}</x-faq-item>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-4">
        <div class="rounded-3xl border-2 border-ink bg-kuning p-8 text-center shadow-[0_6px_0_var(--color-ink)]">
            <h2 class="font-display text-3xl font-extrabold">{{ __('Baca dahulu. Tonton kemudian. Seisi keluarga, setahun.') }}</h2>
            <p class="mt-2 font-display text-4xl font-extrabold">{{ Money::short($price) }}</p>
            <a href="{{ $cta['url'] }}" class="btn-gelap mt-6 text-lg">{{ $cta['label'] }} &rarr;</a>
        </div>
    </section>
</x-layouts.app>
