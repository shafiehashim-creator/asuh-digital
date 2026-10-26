<x-legal-page title="Notis privasi" updated="26 Oktober 2026">
    <section>
        <h2>1. Siapa kami</h2>
        <p>
            ASUH Digital dikendalikan oleh <strong>{{ $companyName }}</strong>@if ($companySsm) ({{ $companySsm }})@endif.
            Notis ini menerangkan bagaimana kami mengumpul dan menggunakan data peribadi anda selaras dengan
            Akta Perlindungan Data Peribadi 2010 (PDPA).
        </p>
    </section>

    <section>
        <h2>2. Data yang kami kumpul</h2>
        <p>Daripada ibu bapa atau penjaga yang mendaftar:</p>
        <ul>
            <li>Nama, alamat e-mel dan nombor telefon.</li>
            <li>Kata laluan akaun, yang disimpan dalam bentuk tersulit (hash). Kami tidak boleh melihatnya.</li>
            <li>Negeri, bilangan anak dan kumpulan umur anak (6–9 atau 10–13 tahun).</li>
            <li>Rekod pesanan dan bayaran, serta resit pindahan bank jika anda memilih kaedah itu.</li>
        </ul>
        <p>
            Tentang anak-anak: akaun adalah milik ibu bapa. Anak tidak mempunyai e-mel atau kata laluan sendiri.
            Kami <strong>tidak</strong> mengumpul nama penuh, foto, tarikh lahir atau sekolah anak. Profil anak hanya
            menyimpan nama panggilan, kumpulan umur dan avatar lukisan yang dipilih.
        </p>
        <p>
            Semasa anak membaca dalam Mod Anak, kami merekod minit membaca, halaman terakhir, buku yang dibaca,
            keputusan kuiz dan lencana yang diperoleh, serta minit menonton video animasi (bagi Langganan Plus). Rekod ini digunakan untuk menyambung bacaan, memberi lencana
            dan menghantar laporan bacaan mingguan kepada ibu bapa (boleh dihentikan pada bila-bila masa).
            Ibu bapa boleh memadam profil anak di halaman Akaun saya, dan semua rekod bacaan anak itu dipadam bersama.
        </p>
        <p>
            Untuk institusi (tadika, sekolah dan pusat tahfiz) yang melanggan lesen, guru mendaftarkan murid dengan
            nama panggilan sahaja. Murid masuk menggunakan kod kelas tanpa e-mel atau kata laluan. Rekod bacaan,
            kuiz dan lencana murid dipaparkan kepada guru kelas dan pentadbir institusi, dan dipadam apabila guru
            memadam murid atau kelas tersebut.
        </p>
        <p>
            Untuk memahami cara laman ini digunakan, kami merekod halaman yang dilawati, jenis peranti (telefon,
            tablet atau komputer) dan laman atau kempen yang membawa anda ke sini. Rekod ini menggunakan nombor rawak
            dalam sesi pelayar anda, <strong>tanpa</strong> alamat IP dan tanpa cookie pihak ketiga, dan dipadam selepas lebih kurang setahun.
        </p>
        <p>
            Butiran kad atau perbankan dalam talian anda dimasukkan terus di halaman bayaran CHIP dan
            <strong>tidak</strong> disimpan oleh kami.
        </p>
    </section>

    <section>
        <h2>3. Tujuan penggunaan</h2>
        <ul>
            <li>Mencipta dan menguruskan akaun anda.</li>
            <li>Memproses pesanan dan bayaran, dan menghantar resit.</li>
            <li>Menghantar e-mel penting tentang akaun, Langganan Tahunan dan pelancaran platform.</li>
            <li>Memahami kumpulan umur pembaca supaya kami menyediakan buku yang sesuai.</li>
            <li>Mematuhi keperluan undang-undang, termasuk rekod perakaunan.</li>
        </ul>
        <p>Kami tidak menjual data anda, dan tidak menggunakannya untuk iklan pihak ketiga.</p>
    </section>

    <section>
        <h2>4. Pihak yang menerima data</h2>
        <ul>
            <li><strong>CHIP</strong> (penyedia gerbang pembayaran), untuk memproses bayaran dalam talian.</li>
            <li>Penyedia hos pelayan dan e-mel kami, yang menyimpan dan menghantar data bagi pihak kami.</li>
            <li>Pihak berkuasa, jika dikehendaki oleh undang-undang.</li>
        </ul>
    </section>

    <section>
        <h2>5. Kuki</h2>
        <p>Kami hanya menggunakan kuki kami sendiri:</p>
        <ul>
            <li>Kuki sesi log masuk dan kuki keselamatan borang, yang perlu untuk laman berfungsi.</li>
            <li>Kuki pilihan bahasa (BM atau English), disimpan selama setahun.</li>
            <li>Kuki rujukan affiliate: jika anda membuka pautan rakan affiliate ASUH, kod rakan itu disimpan untuk tempoh yang ditetapkan (lazimnya 30 hari) supaya pembelian anda dikreditkan kepadanya. Rakan affiliate hanya melihat jenis produk yang dibeli dan tarikhnya, bukan nama atau maklumat anda.</li>
        </ul>
        <p>Kami tidak menggunakan kuki analitik atau pengiklanan pihak ketiga.</p>
    </section>

    <section>
        <h2>6. Tempoh simpanan</h2>
        <p>
            Data akaun disimpan selagi akaun anda aktif. Rekod pesanan dan bayaran disimpan untuk tempoh yang
            dikehendaki oleh undang-undang perakaunan dan cukai, walaupun akaun dipadam.
        </p>
    </section>

    <section>
        <h2>7. Hak anda</h2>
        <p>Anda berhak untuk:</p>
        <ul>
            <li>Mendapatkan salinan data peribadi anda.</li>
            <li>Membetulkan data yang tidak tepat. Nama, e-mel dan telefon boleh dikemas kini sendiri di halaman Akaun saya.</li>
            <li>Menarik balik persetujuan dan meminta akaun serta data anak dipadam.</li>
        </ul>
        <p>
            {{-- Keep a space before Blade directives: "word@if" is not parsed as a directive. --}}
            Untuk membuat permintaan,
            @if ($contactEmail)
                e-mel kami di <a href="mailto:{{ $contactEmail }}" class="pautan">{{ $contactEmail }}</a>.
            @else
                hubungi kami melalui e-mel yang tertera pada resit anda.
            @endif
            Kami akan membalas dalam masa 21 hari.
        </p>
    </section>

    <section>
        <h2>8. Keselamatan</h2>
        <p>
            Sambungan ke laman ini disulitkan (HTTPS). Kata laluan disimpan dalam bentuk hash, dan resit pindahan bank
            disimpan di kawasan peribadi pelayan yang hanya boleh dibuka oleh pentadbir.
        </p>
    </section>

    <section>
        <h2>9. Perubahan pada notis ini</h2>
        <p>Kami akan memaklumkan perubahan penting melalui e-mel atau di laman ini.</p>
    </section>

    @if ($companyAddress || $contactEmail)
        <section>
            <h2>10. Hubungi kami</h2>
            <p>
                {{ $companyName }}<br>
                @if ($companyAddress){!! nl2br(e($companyAddress)) !!}<br>@endif
                @if ($contactEmail)<a href="mailto:{{ $contactEmail }}" class="pautan">{{ $contactEmail }}</a>@endif
            </p>
        </section>
    @endif
</x-legal-page>
