@php
    // The family's own progress through the first steps.
    $hasPass = $activePass !== null || $pendingPass !== null;
    $checklist = [
        ['Langganan aktif', $hasPass, route('account.billing'), 'Lihat langganan'],
        ['Profil anak ditambah', $children->isNotEmpty(), $shelfOpen ? route('children.create') : null, 'Tambah anak'],
        ['PIN ibu bapa ditetapkan', $hasPin, $shelfOpen ? route('account.children') : null, 'Tetapkan PIN'],
        ['Anak sudah mula membaca', $hasRead, $shelfOpen ? route('account.children') : null, 'Masuk Mod Anak'],
    ];
    $done = collect($checklist)->where(1, true)->count();

    // The journey across the top: icon path and label.
    $journey = [
        ['M6 2h12v20l-3-2-3 2-3-2-3 2z', 'Langgan'],
        ['M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M2 21a7 7 0 0 1 14 0', 'Tambah anak'],
        ['M7 11V7a5 5 0 0 1 10 0v4M5 11h14v10H5z', 'Tetapkan PIN'],
        ['M4 4h5a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H4zM20 4h-5a3 3 0 0 0-3 3v13a2 2 0 0 1 2-2h6z', 'Mod Anak'],
        ['M3 3v18h18M7 15l4-4 3 3 5-6', 'Pantau'],
    ];
    $step = 'grid items-center gap-6 rounded-3xl border-2 border-ink/15 bg-white p-6 md:grid-cols-[1fr_auto] md:gap-10';
    $badge = 'grid size-10 shrink-0 place-items-center rounded-full bg-plum font-display text-lg font-extrabold text-white';
    $tip = 'mt-3 rounded-2xl bg-lavender px-4 py-3 text-sm';
@endphp

<x-layouts.account title="Panduan" :$activePass :$pendingPass :$shelfOpen :$plusUpgradePrice>
    {{-- Intro with the family's checklist --}}
    <section class="rounded-3xl border-2 border-ink bg-plum p-6 text-white shadow-[0_4px_0_var(--color-ink)]">
        <h1 class="font-display text-3xl font-extrabold text-kuning">Panduan ASUH Digital</h1>
        <p class="mt-2 max-w-2xl text-white/90">Lima langkah mudah supaya anak anda boleh membaca (dan menonton) dengan selamat, sementara anda memantau kemajuannya.</p>

        <ol class="mt-6 grid grid-cols-5 items-start gap-1 text-center" aria-label="Perjalanan ASUH Digital">
            @foreach ($journey as $i => [$icon, $label])
                <li class="relative flex flex-col items-center gap-2">
                    @unless ($loop->last)
                        <span class="absolute top-6 left-[calc(50%+1.75rem)] hidden h-0.5 w-[calc(100%-3.5rem)] bg-white/40 sm:block" aria-hidden="true"></span>
                    @endunless
                    <span class="grid size-12 place-items-center rounded-full border-2 border-ink bg-kuning text-ink shadow-[0_3px_0_var(--color-ink)]">
                        <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}" /></svg>
                    </span>
                    <span class="text-xs font-bold sm:text-sm"><span class="text-kuning">{{ $i + 1 }}.</span> {{ $label }}</span>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="rounded-3xl border-2 border-ink bg-kuning p-5 shadow-[0_4px_0_var(--color-ink)]" aria-labelledby="checklist-heading">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="checklist-heading" class="font-display text-xl font-extrabold">Senarai semak anda</h2>
            <span class="rounded-full bg-ink px-3 py-1 text-sm font-extrabold text-kuning">{{ $done }} / {{ count($checklist) }} siap</span>
        </div>
        <ul class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($checklist as [$label, $isDone, $href, $action])
                <li class="flex min-h-12 items-center justify-between gap-3 rounded-2xl bg-white px-4 py-2">
                    <span class="flex items-center gap-2 font-bold">
                        @if ($isDone)
                            <span class="grid size-6 place-items-center rounded-full bg-biru text-xs text-white">✓</span>
                        @else
                            <span class="size-6 rounded-full border-2 border-ink/30"></span>
                        @endif
                        <span @class(['text-ink/60 line-through' => $isDone])>{{ $label }}</span>
                    </span>
                    @if (! $isDone && $href)
                        <a href="{{ $href }}" class="pautan text-sm font-bold whitespace-nowrap">{{ $action }} &rarr;</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    {{-- 1. Profiles --}}
    <section class="{{ $step }}" aria-labelledby="step-1">
        <div>
            <div class="flex items-center gap-3"><span class="{{ $badge }}">1</span><h2 id="step-1" class="font-display text-2xl font-extrabold text-plum">Tambah profil anak</h2></div>
            <ol class="mt-4 list-decimal space-y-1 pl-5">
                <li>Buka <strong>Anak-anak</strong> di menu akaun, kemudian tekan <strong>Tambah anak</strong>.</li>
                <li>Isi <strong>nama panggilan</strong> dan pilih <strong>umur</strong>: 6–9 atau 10–13 tahun.</li>
                <li>Pilih <strong>avatar</strong> kegemaran anak (Anis, Anas atau emoji).</li>
            </ol>
            <p class="{{ $tip }}">💡 Sehingga <strong>4 profil anak</strong> dalam satu akaun. Rak buku setiap anak ikut umurnya. Kami tidak menyimpan foto atau tarikh lahir anak.</p>
        </div>
        <x-guide.phone>
            <p class="font-display text-sm font-bold text-plum">Tambah anak</p>
            <div class="rounded-lg border border-ink/20 bg-white px-2 py-1.5"><span class="text-ink/50">Nama panggilan</span><br><strong>Aisyah</strong></div>
            <p class="font-bold">Umur</p>
            <div class="flex gap-1.5"><span class="rounded-full bg-biru px-2.5 py-1 font-bold text-white">6–9</span><span class="rounded-full border border-ink/30 bg-white px-2.5 py-1">10–13</span></div>
            <p class="font-bold">Avatar</p>
            <div class="flex gap-1.5">
                <span class="grid size-9 place-items-center rounded-full border-2 border-biru bg-white text-base">🧕</span>
                <span class="grid size-9 place-items-center rounded-full bg-white text-base">👦</span>
                <span class="grid size-9 place-items-center rounded-full bg-white text-base">🦁</span>
                <span class="grid size-9 place-items-center rounded-full bg-white text-base">🐱</span>
            </div>
            <div class="mt-2 rounded-full border-2 border-ink bg-kuning py-1.5 text-center font-bold">Tambah anak</div>
        </x-guide.phone>
    </section>

    {{-- 2. PIN --}}
    <section class="{{ $step }}" aria-labelledby="step-2">
        <div>
            <div class="flex items-center gap-3"><span class="{{ $badge }}">2</span><h2 id="step-2" class="font-display text-2xl font-extrabold text-plum">Tetapkan PIN ibu bapa</h2></div>
            <ol class="mt-4 list-decimal space-y-1 pl-5">
                <li>Di halaman <strong>Anak-anak</strong>, cari bahagian <strong>PIN ibu bapa</strong>.</li>
                <li>Masukkan <strong>4 digit</strong> yang mudah anda ingat, tetapi sukar anak teka.</li>
                <li>Sahkan dengan kata laluan akaun anda.</li>
            </ol>
            <p class="{{ $tip }}">🔒 PIN ini diperlukan untuk <strong>keluar dari Mod Anak</strong>. Tanpa PIN, anak perlu menjawab soalan darab yang ditulis dalam perkataan.</p>
        </div>
        <x-guide.phone>
            <p class="text-center font-display text-sm font-bold text-plum">PIN ibu bapa</p>
            <div class="flex justify-center gap-2 py-2">@for ($i = 0; $i < 4; $i++)<span @class(['size-4 rounded-full border-2 border-ink', 'bg-ink' => $i < 3])></span>@endfor</div>
            <div class="grid grid-cols-3 gap-1.5 px-3">
                @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, '', 0, '⌫'] as $key)
                    <span @class(['grid h-9 place-items-center rounded-xl text-sm font-bold', 'bg-white shadow-sm' => $key !== ''])>{{ $key }}</span>
                @endforeach
            </div>
        </x-guide.phone>
    </section>

    {{-- 3. Mod Anak --}}
    <section class="{{ $step }}" aria-labelledby="step-3">
        <div>
            <div class="flex items-center gap-3"><span class="{{ $badge }}">3</span><h2 id="step-3" class="font-display text-2xl font-extrabold text-plum">Serahkan peranti dalam Mod Anak</h2></div>
            <ol class="mt-4 list-decimal space-y-1 pl-5">
                <li>Di <strong>Anak-anak</strong> (atau Ringkasan), tekan <strong>▶ Mod Anak</strong> pada nama anak.</li>
                <li>Anak nampak <strong>rak buku sendiri</strong>, ikut umurnya.</li>
                <li>Anak tekan buku untuk membaca. Selak halaman seperti buku sebenar.</li>
            </ol>
            <p class="{{ $tip }}">🛡️ Dalam Mod Anak <strong>tiada harga, butang beli atau tetapan akaun</strong>. Anak tidak boleh keluar ke halaman lain tanpa PIN anda.</p>
        </div>
        <x-guide.phone>
            <div class="flex items-center gap-2 rounded-xl bg-plum p-2 text-white"><span class="text-lg">🧕</span><strong>Hai, Aisyah!</strong></div>
            <p class="font-bold text-plum">Sambung membaca</p>
            <div class="flex items-center gap-2 rounded-xl bg-white p-1.5"><span class="h-10 w-8 rounded bg-oren"></span><span><strong>Celak Ajaib</strong><br>Halaman 12</span></div>
            <p class="font-bold text-plum">Rak buku Aisyah</p>
            <div class="grid grid-cols-3 gap-1.5">
                @foreach (['bg-magenta', 'bg-biru', 'bg-oren', 'bg-plum', 'bg-kuning', 'bg-biru/60'] as $colour)
                    <span class="{{ $colour }} h-14 rounded-md border border-ink/20"></span>
                @endforeach
            </div>
        </x-guide.phone>
    </section>

    {{-- 4. Reading, quizzes and badges --}}
    <section class="{{ $step }}" aria-labelledby="step-4">
        <div>
            <div class="flex items-center gap-3"><span class="{{ $badge }}">4</span><h2 id="step-4" class="font-display text-2xl font-extrabold text-plum">Membaca, kuiz dan lencana</h2></div>
            <ul class="mt-4 list-disc space-y-1 pl-5">
                <li>Buku dibuka semula di <strong>halaman terakhir</strong> yang dibaca.</li>
                <li>Selepas membaca, anak boleh menjawab <strong>kuiz pendek</strong> (lulus 80%).</li>
                <li>Anak dapat <strong>lencana</strong> untuk kuiz yang lulus dan membaca beberapa hari berturut-turut, bukan kerana menyelak halaman laju-laju.</li>
            </ul>
            <p class="{{ $tip }}">⭐ Contoh lencana: <strong>Kuiz Pertama</strong>, <strong>Pembaca Rajin</strong> (3 hari berturut-turut) dan <strong>Seminggu Membaca</strong>.</p>
            <p class="{{ $tip }}">🎁 <strong>Janji ganjaran:</strong> di <strong>Pencapaian</strong>, janjikan hadiah anda sendiri untuk sesuatu lencana (contoh: Seminggu Membaca → aiskrim). Anak nampak janji itu dalam Mod Anak, dan kami e-mel anda bila ia tercapai.</p>
            <p class="{{ $tip }}">👑 <strong>Pembaca Terbaik ASUH:</strong> setiap bulan, 5 pembaca terbaik bagi umur 6–9 dan 10–13 mendapat ganjaran daripada ASUH. Mata dikira daripada minit membaca (maksimum 60 sehari), hari membaca dan kuiz yang lulus.</p>
        </div>
        <x-guide.phone>
            <p class="font-display text-sm font-bold text-plum">Kuiz: Celak Ajaib</p>
            <p class="rounded-xl bg-white p-2">Apa yang Aisyah lakukan sebelum tidur?</p>
            <div class="rounded-xl border-2 border-biru bg-biru/10 p-2 font-bold">✓ Membaca doa</div>
            <div class="rounded-xl bg-white p-2">Bermain telefon</div>
            <div class="rounded-xl bg-white p-2">Menonton TV</div>
            <div class="mt-2 flex items-center gap-2 rounded-xl border-2 border-ink bg-kuning p-2"><span class="text-xl">🏅</span><strong>Lencana baharu: Kuiz Pertama!</strong></div>
        </x-guide.phone>
    </section>

    {{-- 5. Videos (in the subscription that includes them) --}}
    @if ($videosOpen)
        <section class="{{ $step }}" aria-labelledby="step-5">
            <div>
                <div class="flex items-center gap-3"><span class="{{ $badge }}">5</span><h2 id="step-5" class="font-display text-2xl font-extrabold text-plum">Video animasi dan kawalan menonton</h2></div>
                <p class="mt-4">Dengan <strong>{{ app(App\Services\PlusOffer::class)->videoPlanName() }}</strong>, rak Mod Anak ada baris <strong>Video animasi</strong>. Video dimainkan dengan pemain ASUH sendiri: tiada iklan dan tiada pautan keluar ke YouTube.</p>
                <p class="mt-3">Untuk setiap anak, buka <strong>Anak-anak → Ubah</strong> dan pilih:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li><strong>Had menonton sehari</strong>: 15 hingga 90 minit, atau tiada had.</li>
                    <li><strong>Baca dulu, tonton kemudian</strong> (pilihan, dimatikan secara asal): video sesebuah buku hanya dibuka selepas anak membaca buku itu. Video tanpa buku tidak dikunci.</li>
                </ul>
                <p class="{{ $tip }}">🎬 Jika suis tidak dihidupkan, anak boleh terus menonton semua video, mengikut had harian yang anda tetapkan.</p>
            </div>
            <x-guide.phone>
                <p class="font-display text-sm font-bold text-plum">Ubah profil: Aisyah</p>
                <p class="font-bold">Video animasi</p>
                <div class="rounded-lg border border-ink/20 bg-white px-2 py-1.5"><span class="text-ink/50">Had menonton sehari</span><br><strong>30 minit ▾</strong></div>
                <div class="flex items-start gap-2 rounded-lg bg-white p-2">
                    <span class="mt-0.5 grid size-4 shrink-0 place-items-center rounded bg-biru text-[9px] text-white">✓</span>
                    <span><strong>Baca dulu, tonton kemudian</strong> (pilihan)</span>
                </div>
                <div class="relative mt-2 grid h-24 place-items-center rounded-xl bg-plum">
                    <span class="grid size-9 place-items-center rounded-full bg-kuning text-ink">▶</span>
                    <span class="absolute right-1.5 bottom-1.5 rounded bg-ink/70 px-1 text-[9px] text-white">Baki hari ini: 22 minit</span>
                </div>
            </x-guide.phone>
        </section>
    @endif

    {{-- Leaving Mod Anak --}}
    <section class="{{ $step }}" aria-labelledby="step-exit">
        <div>
            <div class="flex items-center gap-3"><span class="{{ $badge }}">{{ $videosOpen ? 6 : 5 }}</span><h2 id="step-exit" class="font-display text-2xl font-extrabold text-plum">Keluar dari Mod Anak</h2></div>
            <ol class="mt-4 list-decimal space-y-1 pl-5">
                <li>Tekan <strong>Keluar</strong> di bahagian atas rak anak.</li>
                <li>Masukkan <strong>PIN ibu bapa</strong> (atau jawab soalan darab jika belum ada PIN).</li>
                <li>Anda kembali ke akaun ibu bapa. Tukar anak atau semak kemajuan.</li>
            </ol>
            <p class="{{ $tip }}">👪 Ada beberapa anak? Keluar dahulu, kemudian masuk Mod Anak untuk anak seterusnya.</p>
        </div>
        <x-guide.phone>
            <div class="grid h-full place-items-center pt-6 text-center">
                <div class="space-y-3">
                    <span class="mx-auto grid size-14 place-items-center rounded-full bg-plum text-2xl text-white">🔒</span>
                    <p class="font-display text-sm font-bold text-plum">Untuk ibu bapa sahaja</p>
                    <p>Masukkan PIN untuk keluar</p>
                    <div class="flex justify-center gap-2">@for ($i = 0; $i < 4; $i++)<span class="size-4 rounded-full border-2 border-ink"></span>@endfor</div>
                </div>
            </div>
        </x-guide.phone>
    </section>

    {{-- Following progress --}}
    <section class="{{ $step }}" aria-labelledby="step-progress">
        <div>
            <div class="flex items-center gap-3"><span class="{{ $badge }}">{{ $videosOpen ? 7 : 6 }}</span><h2 id="step-progress" class="font-display text-2xl font-extrabold text-plum">Pantau kemajuan anak</h2></div>
            <ul class="mt-4 list-disc space-y-1 pl-5">
                <li><strong>Pencapaian</strong>: minit membaca setiap hari minggu ini, kuiz dan lencana setiap anak.</li>
                <li><strong>Laporan mingguan</strong> dihantar ke e-mel anda setiap Isnin pagi jika anak membaca minggu lalu.</li>
                <li>Matikan laporan e-mel bila-bila masa di <strong>Profil dan keselamatan</strong>.</li>
            </ul>
            @if ($shelfOpen)
                <a href="{{ route('account.progress') }}" class="btn-gelap mt-4 text-sm">Lihat pencapaian</a>
            @endif
        </div>
        <x-guide.phone>
            <p class="font-display text-sm font-bold text-plum">Minggu ini: Aisyah</p>
            <div class="flex h-28 items-end justify-between gap-1 rounded-xl bg-white p-2">
                @foreach (['I' => 40, 'S' => 70, 'R' => 25, 'K' => 85, 'J' => 55, 'S ' => 95, 'A' => 30] as $day => $height)
                    <span class="flex flex-1 flex-col items-center gap-1"><span class="w-full rounded-t bg-biru" style="height: {{ (int) round($height * 0.8) }}px"></span><span class="text-[9px]">{{ trim($day) }}</span></span>
                @endforeach
            </div>
            <div class="grid grid-cols-2 gap-1.5">
                <div class="rounded-xl bg-white p-2"><strong class="font-display text-lg text-plum">96</strong><br>minit membaca</div>
                <div class="rounded-xl bg-white p-2"><strong class="font-display text-lg text-plum">3</strong><br>lencana</div>
            </div>
            <div class="flex items-center gap-2 rounded-xl border border-ink/20 bg-white p-2">✉️ <span><strong>Laporan mingguan</strong><br>Setiap Isnin, 8 pagi</span></div>
        </x-guide.phone>
    </section>

    {{-- Install the app --}}
    <section class="{{ $step }}" aria-labelledby="step-install">
        <div>
            <div class="flex items-center gap-3"><span class="{{ $badge }}">{{ $videosOpen ? 8 : 7 }}</span><h2 id="step-install" class="font-display text-2xl font-extrabold text-plum">Pasang app di telefon atau tablet anak</h2></div>
            <ul class="mt-4 list-disc space-y-1 pl-5">
                <li><strong>Android, Chrome atau Edge</strong>: tekan butang <strong>Pasang app</strong>.</li>
                <li><strong>iPhone atau iPad</strong>: buka di Safari, tekan <strong>Kongsi</strong>, kemudian <strong>Tambah ke Skrin Utama</strong>.</li>
                <li>Ikon ASUH muncul di skrin utama. Tekan lama ikon untuk pintasan terus ke Mod Anak.</li>
            </ul>
            <x-install-app class="mt-4" />
            <a href="{{ route('install') }}" class="pautan mt-3 inline-block text-sm font-bold">Panduan pasang app lengkap &rarr;</a>
        </div>
        <x-guide.phone>
            <div class="grid grid-cols-4 gap-3 p-2 pt-4">
                @foreach (range(1, 11) as $i)
                    <span class="aspect-square rounded-xl bg-ink/10"></span>
                @endforeach
                <span class="flex flex-col items-center gap-1">
                    <img src="/icons/icon-192.png" alt="" class="aspect-square w-full rounded-xl ring-2 ring-kuning">
                    <span class="text-[9px] font-bold">ASUH</span>
                </span>
            </div>
        </x-guide.phone>
    </section>

    <section class="rounded-3xl border-2 border-ink/15 bg-white p-6 text-center">
        <h2 class="font-display text-xl font-extrabold text-plum">Masih ada soalan?</h2>
        <p class="mt-1 text-ink/80">Hubungi kami di WhatsApp (butang hijau di bawah) atau lihat <a href="{{ url('/') }}#soalan" class="pautan font-bold">soalan lazim</a>.</p>
    </section>
</x-layouts.account>
