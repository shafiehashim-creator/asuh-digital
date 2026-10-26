<x-legal-page title="Refund policy" updated="26 October 2026">
    <section>
        <h2>1. Our policy</h2>
        <p>{{ $refundPolicy }}</p>
        <p>If this English version and the Bahasa Melayu version differ, the Bahasa Melayu version prevails.</p>
    </section>

    <section>
        <h2>2. What it covers</h2>
        <ul>
            <li>The Yearly Subscription and the Plus Subscription, including the early bird pre-order.</li>
            <li>E-books bought singly.</li>
            <li>Double or excess payments caused by technical problems are refunded in full.</li>
        </ul>
    </section>

    <section>
        <h2>3. How to ask</h2>
        <ul>
            <li>
                E-mail us
                @if ($contactEmail)
                    at <a href="mailto:{{ $contactEmail }}" class="pautan">{{ $contactEmail }}</a>
                @endif
                from your account's e-mail address.
            </li>
            <li>Give your order reference (for example ASUH-…), shown on your e-mail receipt and in My account → Subscription and orders.</li>
            <li>Briefly give the reason for your request.</li>
        </ul>
    </section>

    <section>
        <h2>4. Timing and method</h2>
        <ul>
            <li>We reply to your request within 3 working days.</li>
            <li>Approved refunds are processed within 14 working days.</li>
            <li>Card payments are refunded to the same card. FPX, e-wallet and bank transfer payments are refunded to the bank account you give us.</li>
            <li>How long the money takes to reach you depends on your bank or card provider.</li>
        </ul>
    </section>

    <section>
        <h2>5. After a refund</h2>
        <p>Access to the refunded subscription or e-book ends. Your children's reading records stay in your account.</p>
    </section>

    <section>
        <h2>6. Contact us</h2>
        <p>
            {{ $companyName }}@if ($companySsm) ({{ $companySsm }})@endif.
            @if ($companyAddress) {{ $companyAddress }}. @endif
            @if ($contactEmail) E-mail: <a href="mailto:{{ $contactEmail }}" class="pautan">{{ $contactEmail }}</a>. @endif
        </p>
    </section>
</x-legal-page>
