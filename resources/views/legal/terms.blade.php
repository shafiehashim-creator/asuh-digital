@php use App\Enums\PassStartRule; @endphp

<x-legal-page title="Terma dan syarat" updated="24 Oktober 2026">
    <section>
        <h2>1. Tentang perkhidmatan</h2>
        <p>
            ASUH Digital ialah platform e-book kanak-kanak Islamik untuk umur 6 hingga 13 tahun, dikendalikan oleh
            <strong>{{ $companyName }}</strong>. Dengan membuat pesanan, anda bersetuju dengan terma ini.
        </p>
    </section>

    <section>
        <h2>2. Akaun</h2>
        <ul>
            <li>Akaun mesti didaftarkan oleh ibu bapa atau penjaga yang berumur 18 tahun ke atas.</li>
            <li>Anda bertanggungjawab menjaga kerahsiaan kata laluan dan memastikan maklumat akaun tepat.</li>
            <li>Satu akaun adalah untuk satu keluarga, dengan sehingga 4 profil anak.</li>
        </ul>
    </section>

    <section>
        <h2>3. Pre-order Langganan Tahunan</h2>
        <ul>
            <li>Harga early bird terhad kepada {{ $slotLimit }} slot, dan satu slot bagi setiap akaun.</li>
            <li>Slot ditempah untuk tempoh terhad semasa anda membuat bayaran. Jika bayaran tidak diterima dalam tempoh itu, slot dilepaskan kepada pembeli lain.</li>
            <li>Harga yang dikenakan ialah harga yang dipaparkan semasa anda membuat pesanan.</li>
        </ul>
    </section>

    <section>
        <h2>4. Bayaran</h2>
        <ul>
            <li>Bayaran dibuat melalui CHIP (FPX, kad atau e-wallet) atau pindahan bank.</li>
            <li>Langganan Tahunan diaktifkan selepas bayaran disahkan: secara automatik untuk CHIP, atau selepas kami menyemak resit untuk pindahan bank.</li>
            <li>Bayaran dibuat sekali bagi tempoh 12 bulan. <strong>Tiada caj berulang automatik.</strong></li>
        </ul>
    </section>

    <section>
        <h2>5. Tempoh Langganan Tahunan</h2>
        <p>
            @if ($passStartRule === PassStartRule::OnLaunch)
                Tempoh 12 bulan bermula pada tarikh platform ASUH Digital dilancarkan. Kami akan memaklumkan tarikhnya melalui e-mel.
            @else
                Tempoh 12 bulan bermula sebaik sahaja bayaran anda disahkan.
            @endif
        </p>
    </section>

    <section>
        <h2>6. Bayaran balik</h2>
        <p>{{ $refundPolicy }}</p>
    </section>

    <section>
        <h2>7. Penggunaan kandungan</h2>
        <ul>
            <li>Kandungan adalah untuk bacaan peribadi keluarga anda sahaja.</li>
            <li>Anda tidak dibenarkan menyalin, memuat turun secara tidak sah, mengedar atau berkongsi akses akaun dengan orang di luar keluarga anda.</li>
            <li>Hak cipta semua kandungan dimiliki oleh {{ $companyName }} atau pemberi lesennya.</li>
        </ul>
    </section>

    <section>
        <h2>8. Perubahan dan gangguan perkhidmatan</h2>
        <p>
            Kami boleh menambah, mengubah atau mengeluarkan tajuk buku dari semasa ke semasa. Kami berusaha memastikan
            platform sentiasa tersedia, tetapi gangguan sementara untuk penyelenggaraan mungkin berlaku.
        </p>
    </section>

    <section>
        <h2>9. Penamatan akaun</h2>
        <p>Kami boleh menggantung akaun yang melanggar terma ini, contohnya berkongsi akaun secara meluas atau mengedar kandungan.</p>
    </section>

    <section>
        <h2>10. Undang-undang</h2>
        <p>Terma ini tertakluk kepada undang-undang Malaysia.</p>
    </section>

    <section>
        <h2>11. Hubungi kami</h2>
        <p>
            Untuk sebarang pertanyaan,
            @if ($contactEmail)
                e-mel <a href="mailto:{{ $contactEmail }}" class="pautan">{{ $contactEmail }}</a>.
            @else
                hubungi kami melalui e-mel yang tertera pada resit anda.
            @endif
            Lihat juga <a href="{{ route('privacy') }}" class="pautan">notis privasi</a> kami.
        </p>
    </section>
</x-legal-page>
