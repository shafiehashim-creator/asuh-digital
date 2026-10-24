@php
    use App\Enums\PassStartRule;
    use App\Support\Money;

    $checkoutUrl = Route::has('preorder.create') ? route('preorder.create') : '#harga';
    $pilotUrl = Route::has('pilot.create') ? route('pilot.create') : null;
    $categories = ['Komik', 'Kisah nabi', 'Akhlak', 'Doa harian', 'Cerita bergambar', 'Novel kanak-kanak', 'Sirah'];
@endphp

<x-layouts.app>
    {{-- Hero --}}
    <section class="overflow-hidden">
        <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-12 md:grid-cols-[1.1fr_1fr] md:py-16">
            <div>
                <p class="inline-block rounded-full border-2 border-ink bg-oren px-3 py-1 text-sm font-extrabold text-ink">
                    Pre-order · Harga early bird
                </p>
                <h1 class="mt-4 font-display text-4xl leading-tight font-extrabold text-magenta sm:text-5xl">{{ $headline }}</h1>
                <p class="mt-4 text-lg text-ink/80">{{ $subheadline }}</p>

                @if ($plan)
                    <div class="mt-6 flex flex-wrap items-end gap-x-4 gap-y-1">
                        <p class="font-display text-5xl font-extrabold text-plum">{{ Money::short($plan->price_sen) }}</p>
                        @if ($regularPlan && $regularPlan->price_sen > $plan->price_sen)
                            <p class="pb-2 text-xl font-bold text-ink/70 line-through" aria-label="Harga biasa {{ Money::short($regularPlan->price_sen) }}">
                                {{ Money::short($regularPlan->price_sen) }}
                            </p>
                        @endif
                        <p class="w-full text-sm font-semibold text-ink/70">Pas Tahunan · 12 bulan · sekali bayar, tiada caj automatik</p>
                    </div>
                @endif

                <x-slot-counter :limit="$slotLimit" :remaining="$slotsRemaining" class="mt-6 max-w-md" />

                <div class="mt-6 flex flex-wrap gap-3">
                    @if ($soldOut)
                        <span class="btn-utama" aria-disabled="true">Slot early bird telah habis</span>
                    @else
                        <a href="{{ $checkoutUrl }}" class="btn-utama text-lg">Tempah slot saya</a>
                    @endif
                    <a href="#soalan" class="btn-gelap">Soalan lazim</a>
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

    {{-- Categories --}}
    <section aria-label="Kategori buku" class="border-y-2 border-ink bg-plum">
        <ul class="mx-auto flex max-w-6xl flex-wrap justify-center gap-2 px-4 py-5">
            @foreach ($categories as $category)
                <li class="rounded-full bg-white/10 px-4 py-1.5 text-sm font-bold text-white">{{ $category }}</li>
            @endforeach
        </ul>
    </section>

    {{-- Why ASUH --}}
    <section class="mx-auto max-w-6xl px-4 py-16">
        <h2 class="text-center font-display text-3xl font-extrabold text-plum sm:text-4xl">Kenapa ibu bapa pilih ASUH Digital</h2>
        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Disemak syariah', 'Setiap buku mesti lulus semakan syariah sebelum diterbitkan.'],
                ['Ikut umur anak', 'Rak buku disusun untuk 6–9 tahun dan 10–13 tahun, jadi anak jumpa bacaan yang sesuai.'],
                ['Mod Anak yang selamat', 'Tiada harga, butang beli atau tetapan. Keluar hanya melalui pintu ibu bapa.'],
                ['Seisi keluarga', 'Satu Pas untuk sehingga 4 profil anak, di telefon, tablet atau komputer.'],
            ] as [$title, $body])
                <div class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
                    <h3 class="font-display text-xl font-bold text-plum">{{ $title }}</h3>
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
            <p class="inline-block rounded-full border-2 border-ink bg-oren px-3 py-0.5 text-sm font-extrabold text-ink">Harga early bird</p>
            <h2 class="mt-2 font-display text-3xl font-extrabold text-plum">Pas Tahunan ASUH Digital</h2>

            @if ($plan)
                <p class="mt-4 font-display text-5xl font-extrabold text-magenta">
                    {{ Money::short($plan->price_sen) }}
                    @if ($regularPlan && $regularPlan->price_sen > $plan->price_sen)
                        <span class="text-2xl text-ink/70 line-through">{{ Money::short($regularPlan->price_sen) }}</span>
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

            <x-slot-counter :limit="$slotLimit" :remaining="$slotsRemaining" class="mt-6" />

            @if ($soldOut)
                <p class="mt-6 rounded-xl bg-ink/5 p-4 font-semibold">
                    Slot early bird telah habis. Terima kasih atas sokongan anda! Pas Tahunan akan dijual pada harga biasa apabila platform dilancarkan.
                </p>
            @else
                <a href="{{ $checkoutUrl }}" class="btn-utama mt-6 w-full text-lg">Tempah slot saya</a>
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

            <x-faq-item question="Bila Pas Tahunan saya bermula?">
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
    @unless ($soldOut)
        <section class="mx-auto max-w-6xl px-4 pb-4">
            <div class="rounded-3xl border-2 border-ink bg-kuning p-8 text-center shadow-[0_6px_0_var(--color-ink)]">
                <h2 class="font-display text-3xl font-extrabold text-ink">Jom Jadi Baik bersama ASUH!</h2>
                <p class="mt-2 font-semibold text-ink/80">Tempah slot early bird sebelum habis.</p>
                <a href="{{ $checkoutUrl }}" class="btn-gelap mt-6 text-lg">Tempah slot saya</a>
            </div>
        </section>
    @endunless
</x-layouts.app>
