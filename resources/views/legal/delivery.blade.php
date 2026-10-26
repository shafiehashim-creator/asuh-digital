<x-legal-page title="Polisi penghantaran" updated="26 Oktober 2026">
    <section>
        <h2>1. Produk digital sahaja</h2>
        <p>
            Semua produk ASUH Digital ialah produk digital: Langganan Tahunan, Langganan Plus dan e-book single.
            <strong>Tiada barang fizikal dihantar</strong>, jadi tiada caj penghantaran.
        </p>
    </section>

    <section>
        <h2>2. Cara produk diterima</h2>
        <ul>
            <li>Produk dibaca dalam talian di laman ASUH Digital melalui akaun anda, menggunakan pelayar web di telefon, tablet atau komputer.</li>
            <li>E-book tidak dimuat turun sebagai fail dan tidak dihantar melalui e-mel.</li>
            <li>Resit dihantar ke alamat e-mel akaun anda sebaik bayaran disahkan.</li>
        </ul>
    </section>

    <section>
        <h2>3. Bila akses dibuka</h2>
        <ul>
            <li><strong>CHIP (FPX, kad, e-wallet):</strong> serta-merta selepas bayaran berjaya.</li>
            <li><strong>Pindahan bank:</strong> selepas kami menyemak resit yang anda muat naik, biasanya dalam tempoh 1 hari bekerja.</li>
            <li>
                <strong>Pre-order:</strong>
                @if ($startsOnLaunch)
                    akses dibuka pada tarikh platform ASUH Digital dilancarkan. Kami akan memaklumkan tarikhnya melalui e-mel.
                @else
                    akses dibuka sebaik bayaran disahkan.
                @endif
            </li>
        </ul>
    </section>

    <section>
        <h2>4. Kawasan</h2>
        <p>Perkhidmatan boleh digunakan di mana-mana tempat yang mempunyai sambungan Internet. Harga dalam Ringgit Malaysia (RM).</p>
    </section>

    <section>
        <h2>5. Masalah akses</h2>
        <p>
            Jika akses belum dibuka selepas tempoh di atas, hubungi kami
            @if ($contactEmail)
                di <a href="mailto:{{ $contactEmail }}" class="pautan">{{ $contactEmail }}</a>
            @endif
            dengan nombor rujukan pesanan anda. Lihat juga <a href="{{ route('refund') }}" class="pautan">Polisi bayaran balik</a>.
        </p>
    </section>
</x-legal-page>
