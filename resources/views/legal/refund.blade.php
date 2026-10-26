<x-legal-page title="Polisi bayaran balik" updated="26 Oktober 2026">
    <section>
        <h2>1. Polisi kami</h2>
        <p>{{ $refundPolicy }}</p>
    </section>

    <section>
        <h2>2. Apa yang dilindungi</h2>
        <ul>
            <li>Langganan Tahunan dan Langganan Plus, termasuk pre-order early bird.</li>
            <li>E-book yang dibeli secara single.</li>
            <li>Bayaran berganda atau bayaran yang terlebih kerana masalah teknikal akan dipulangkan sepenuhnya.</li>
        </ul>
    </section>

    <section>
        <h2>3. Cara memohon</h2>
        <ul>
            <li>
                E-mel kami
                @if ($contactEmail)
                    di <a href="mailto:{{ $contactEmail }}" class="pautan">{{ $contactEmail }}</a>
                @endif
                daripada alamat e-mel akaun anda.
            </li>
            <li>Nyatakan nombor rujukan pesanan (contohnya ASUH-…), yang tertera pada resit e-mel dan di Akaun saya → Langganan dan pesanan.</li>
            <li>Nyatakan sebab permohonan secara ringkas.</li>
        </ul>
    </section>

    <section>
        <h2>4. Tempoh dan kaedah bayaran balik</h2>
        <ul>
            <li>Kami akan membalas permohonan anda dalam tempoh 3 hari bekerja.</li>
            <li>Bayaran balik yang diluluskan diproses dalam tempoh 14 hari bekerja.</li>
            <li>Bayaran kad dipulangkan ke kad yang sama. Bayaran FPX, e-wallet dan pindahan bank dipulangkan ke akaun bank yang anda maklumkan kepada kami.</li>
            <li>Tempoh wang sampai ke akaun anda bergantung pada bank atau penyedia kad anda.</li>
        </ul>
    </section>

    <section>
        <h2>5. Selepas bayaran balik</h2>
        <p>Akses kepada langganan atau e-book yang dipulangkan bayarannya akan ditamatkan. Rekod bacaan anak-anak kekal dalam akaun anda.</p>
    </section>

    <section>
        <h2>6. Hubungi kami</h2>
        <p>
            {{ $companyName }}@if ($companySsm) ({{ $companySsm }})@endif.
            @if ($companyAddress) {{ $companyAddress }}. @endif
            @if ($contactEmail) E-mel: <a href="mailto:{{ $contactEmail }}" class="pautan">{{ $contactEmail }}</a>. @endif
        </p>
    </section>
</x-legal-page>
