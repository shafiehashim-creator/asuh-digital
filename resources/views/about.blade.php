<x-layouts.app title="Tentang kami">
    {{-- Who we are --}}
    <section class="mx-auto grid max-w-6xl items-center gap-8 px-4 py-12 md:grid-cols-[1fr_auto] md:py-16">
        <div>
            <p class="inline-block rounded-full border-2 border-ink bg-kuning px-3 py-0.5 text-sm font-extrabold">Tentang kami</p>
            <h1 class="mt-3 font-display text-4xl font-extrabold text-magenta sm:text-5xl">Jom Jadi Baik!</h1>
            <p class="mt-4 max-w-2xl text-lg text-ink/85">
                ASUH Digital ialah perpustakaan e-book kanak-kanak Islamik untuk anak berumur 6 hingga 13 tahun.
                Kami membantu ibu bapa dan guru memupuk minat membaca, sambil menanam akhlak yang baik melalui kisah yang dekat dengan dunia anak-anak.
            </p>
            <p class="mt-3 max-w-2xl text-ink/80">
                ASUH ialah jenama buku kanak-kanak di bawah <strong>Galeri Ilmu Media Group</strong>. Buku-buku ASUH kini boleh dibaca di telefon, tablet dan komputer, di rumah atau di kelas.
            </p>
        </div>
        <div class="grid grid-cols-2 gap-3 md:w-80" aria-hidden="true">
            @foreach (['anis', 'anas'] as $image)
                <img src="{{ Vite::asset("resources/images/brand/{$image}.webp") }}" alt=""
                    class="aspect-[3/4] w-full rounded-3xl border-2 border-ink object-cover object-top shadow-[0_4px_0_var(--color-ink)]">
            @endforeach
        </div>
    </section>

    {{-- What we stand for --}}
    <section class="bg-white py-12 md:py-16">
        <div class="mx-auto max-w-6xl px-4">
            <h2 class="font-display text-3xl font-extrabold text-plum">Apa yang kami pegang</h2>
            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['📖', 'Membaca sebagai kebiasaan', 'Rak ikut umur, rekod bacaan dan lencana yang diberi atas konsistensi membaca dan kuiz yang dijawab betul, bukan bilangan halaman yang diselak.'],
                    ['🕌', 'Disemak syariah', 'Setiap buku dan video disemak sebelum diterbitkan. Kandungan yang belum lulus semakan tidak akan sampai kepada anak anda.'],
                    ['🛡️', 'Privasi anak dijaga', 'Akaun milik ibu bapa. Kami hanya menyimpan nama panggilan dan kumpulan umur anak, tanpa foto, tarikh lahir atau nama sekolah.'],
                    ['👨‍👩‍👧', 'Ibu bapa memegang kawalan', 'Mod Anak tanpa harga atau butang beli, pintu ibu bapa untuk keluar, dan laporan bacaan mingguan melalui e-mel.'],
                ] as [$icon, $title, $text])
                    <li class="rounded-3xl border-2 border-ink bg-latar p-5 shadow-[0_4px_0_var(--color-ink)]">
                        <span class="text-3xl" aria-hidden="true">{{ $icon }}</span>
                        <h3 class="mt-3 font-display text-lg font-bold text-plum">{{ $title }}</h3>
                        <p class="mt-1 text-sm text-ink/80">{{ $text }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Who it is for --}}
    <section class="mx-auto max-w-6xl px-4 py-12 md:py-16">
        <h2 class="font-display text-3xl font-extrabold text-plum">Untuk siapa</h2>
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <div class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
                <h3 class="font-display text-xl font-bold">Keluarga</h3>
                <p class="mt-2 text-ink/80">Satu Langganan Tahunan membuka semua e-book ASUH untuk sehingga 4 anak, setiap seorang dengan rak ikut umur sendiri.</p>
                <a href="{{ route('pass.show') }}" class="btn-utama mt-4">Lihat Langganan Tahunan</a>
            </div>
            <div class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
                <h3 class="font-display text-xl font-bold">Tadika, sekolah agama, KAFA dan tahfiz</h3>
                <p class="mt-2 text-ink/80">Lesen institusi dengan portal guru, kod kelas untuk murid tanpa akaun, dan laporan kemajuan bacaan setiap murid.</p>
                <a href="{{ route('pilot.create') }}" class="btn-gelap mt-4">Mohon pilot institusi</a>
            </div>
        </div>
    </section>

    {{-- Company details --}}
    <section class="mx-auto max-w-6xl px-4 pb-4">
        <div class="rounded-3xl border-2 border-ink bg-plum p-6 text-white shadow-[0_4px_0_var(--color-ink)] sm:p-8">
            <h2 class="font-display text-2xl font-bold text-kuning">Hubungi kami</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-bold text-white/70">Syarikat</dt>
                    <dd>{{ $companyName }}@if ($companySsm) <span class="text-white/80">({{ $companySsm }})</span>@endif</dd>
                </div>
                @if ($companyAddress)
                    <div>
                        <dt class="text-sm font-bold text-white/70">Alamat</dt>
                        <dd class="whitespace-pre-line">{{ $companyAddress }}</dd>
                    </div>
                @endif
                @if ($contactEmail)
                    <div>
                        <dt class="text-sm font-bold text-white/70">E-mel</dt>
                        <dd><a href="mailto:{{ $contactEmail }}" class="font-bold underline underline-offset-2">{{ $contactEmail }}</a></dd>
                    </div>
                @endif
                @if ($whatsapp)
                    <div>
                        <dt class="text-sm font-bold text-white/70">WhatsApp</dt>
                        <dd><a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="font-bold underline underline-offset-2">+{{ $whatsapp }}</a></dd>
                    </div>
                @endif
            </dl>
        </div>
    </section>
</x-layouts.app>
