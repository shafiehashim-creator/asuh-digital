@php
    $card = 'rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]';
    $platforms = [
        ['📱', __('Android (Chrome)'), [
            __('Buka laman ASUH Digital dalam Chrome.'),
            __('Tekan butang Pasang app ASUH di atas, atau menu ⋮ kemudian "Pasang aplikasi".'),
            __('Ikon ASUH muncul di skrin utama. Tekan untuk buka.'),
        ]],
        ['🍎', __('iPhone dan iPad (Safari)'), [
            __('Buka laman ASUH Digital dalam Safari.'),
            __('Tekan butang Kongsi (petak dengan anak panah ke atas).'),
            __('Pilih "Tambah ke Skrin Utama", kemudian tekan "Tambah".'),
        ]],
        ['💻', __('Laptop dan komputer (Chrome atau Edge)'), [
            __('Buka laman ASUH Digital dalam Chrome atau Microsoft Edge.'),
            __('Tekan ikon pasang di hujung bar alamat, atau butang Pasang app ASUH di atas.'),
            __('ASUH dibuka dalam tetingkapnya sendiri, tanpa bar alamat.'),
        ]],
    ];
@endphp

<x-layouts.app title="Pasang app ASUH">
    <section class="bg-plum text-white">
        <div class="mx-auto max-w-3xl px-4 py-12 text-center">
            <img src="/icons/icon-192.png" alt="" width="96" height="96" class="mx-auto size-24 rounded-3xl border-2 border-ink shadow-[0_4px_0_var(--color-ink)]">
            <h1 class="mt-5 font-display text-4xl leading-tight font-extrabold text-kuning sm:text-5xl">{{ __('Pasang app ASUH') }}</h1>
            <p class="mx-auto mt-3 max-w-xl text-lg text-white/90">
                {{ __('ASUH Digital di skrin utama telefon, tablet atau laptop anda. Tiada muat turun dari Play Store atau App Store, dan hampir tiada ruang storan digunakan.') }}
            </p>

            <div class="mt-6 flex justify-center">
                <x-install-app />
            </div>
            <p x-data="installApp()" x-show="installed" x-cloak class="mt-6 font-bold text-kuning">✓ {{ __('ASUH sudah dipasang di peranti ini.') }}</p>
        </div>
    </section>

    <div class="mx-auto max-w-5xl space-y-10 px-4 py-12">
        <section aria-labelledby="how-heading">
            <h2 id="how-heading" class="font-display text-2xl font-extrabold text-plum">{{ __('Cara memasang') }}</h2>
            <div class="mt-5 grid grid-cols-1 gap-6 md:grid-cols-3">
                @foreach ($platforms as [$icon, $name, $steps])
                    <div class="{{ $card }} min-w-0">
                        <p class="text-3xl" aria-hidden="true">{{ $icon }}</p>
                        <h3 class="mt-2 font-display text-xl font-bold">{{ $name }}</h3>
                        <ol class="mt-3 space-y-2">
                            @foreach ($steps as $step)
                                <li class="flex gap-3">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-kuning text-sm font-extrabold">{{ $loop->iteration }}</span>
                                    <span class="text-ink/90">{{ $step }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="{{ $card }}" aria-labelledby="tips-heading">
            <h2 id="tips-heading" class="font-display text-2xl font-extrabold text-plum">{{ __('Tip') }}</h2>
            <ul class="mt-4 space-y-3">
                @foreach ([
                    __('Tekan lama ikon ASUH untuk terus ke Mod Anak, Video atau Katalog.'),
                    __('App sentiasa versi terkini. Tiada kemas kini yang perlu dimuat turun.'),
                    __('Log masuk sekali sahaja. Akaun, langganan dan rekod bacaan anak sama seperti di laman web.'),
                    __('Perlukan internet untuk membaca dan menonton. Buku dan video tidak disimpan dalam peranti, demi keselamatan kandungan.'),
                ] as $tip)
                    <li class="flex gap-2"><span class="font-bold text-biru" aria-hidden="true">✓</span> {{ $tip }}</li>
                @endforeach
            </ul>
        </section>
    </div>
</x-layouts.app>
