<x-legal-page title="Delivery policy" updated="26 October 2026">
    <section>
        <h2>1. Digital products only</h2>
        <p>
            Every ASUH Digital product is digital: the Yearly Subscription, the Plus Subscription and single e-books.
            <strong>Nothing physical is shipped</strong>, so there are no delivery charges.
            If this English version and the Bahasa Melayu version differ, the Bahasa Melayu version prevails.
        </p>
    </section>

    <section>
        <h2>2. How you receive them</h2>
        <ul>
            <li>Products are read online on the ASUH Digital site through your account, in a web browser on a phone, tablet or computer.</li>
            <li>E-books are not downloaded as files and are not sent by e-mail.</li>
            <li>A receipt is sent to your account's e-mail address as soon as your payment is confirmed.</li>
        </ul>
    </section>

    <section>
        <h2>3. When access opens</h2>
        <ul>
            <li><strong>CHIP (FPX, card, e-wallet):</strong> straight after a successful payment.</li>
            <li><strong>Bank transfer:</strong> after we check the receipt you upload, usually within 1 working day.</li>
            <li>
                <strong>Pre-order:</strong>
                @if ($startsOnLaunch)
                    access opens on the day the ASUH Digital platform launches. We will e-mail you the date.
                @else
                    access opens as soon as your payment is confirmed.
                @endif
            </li>
        </ul>
    </section>

    <section>
        <h2>4. Where</h2>
        <p>The service can be used anywhere with an internet connection. Prices are in Malaysian Ringgit (RM).</p>
    </section>

    <section>
        <h2>5. Access problems</h2>
        <p>
            If access has not opened after the times above, contact us
            @if ($contactEmail)
                at <a href="mailto:{{ $contactEmail }}" class="pautan">{{ $contactEmail }}</a>
            @endif
            with your order reference. See also our <a href="{{ route('refund') }}" class="pautan">refund policy</a>.
        </p>
    </section>
</x-legal-page>
