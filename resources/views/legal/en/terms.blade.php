@php use App\Enums\PassStartRule; @endphp

<x-legal-page title="Terms and conditions" updated="24 October 2026">
    <section>
        <h2>1. About the service</h2>
        <p>
            ASUH Digital is an Islamic children's e-book platform for ages 6 to 13, operated by
            <strong>{{ $companyName }}</strong>. By placing an order, you agree to these terms. If this English
            version and the Bahasa Melayu version differ, the Bahasa Melayu version prevails.
        </p>
    </section>

    <section>
        <h2>2. Accounts</h2>
        <ul>
            <li>Accounts must be registered by a parent or guardian aged 18 or over.</li>
            <li>You are responsible for keeping your password secret and your account details accurate.</li>
            <li>One account is for one family, with up to 4 child profiles.</li>
        </ul>
    </section>

    <section>
        <h2>3. Yearly Subscription pre-order</h2>
        <ul>
            <li>The early bird price is limited to {{ $slotLimit }} slots, and one slot per account.</li>
            <li>A slot is held for a limited time while you pay. If payment is not received in that time, the slot is released to other buyers.</li>
            <li>The price charged is the price shown when you place your order.</li>
        </ul>
    </section>

    <section>
        <h2>4. Payment</h2>
        <ul>
            <li>Payment is made through CHIP (FPX, card or e-wallet) or by bank transfer.</li>
            <li>The Yearly Subscription is activated once payment is confirmed: automatically for CHIP, or after we check the receipt for bank transfers.</li>
            <li>Payment is made once for 12 months. <strong>There are no automatic recurring charges.</strong></li>
        </ul>
    </section>

    <section>
        <h2>5. Yearly Subscription period</h2>
        <p>
            @if ($passStartRule === PassStartRule::OnLaunch)
                The 12 months start on the day the ASUH Digital platform launches. We will e-mail you the date.
            @else
                The 12 months start as soon as your payment is confirmed.
            @endif
        </p>
    </section>

    <section>
        <h2>6. Refunds</h2>
        <p>{{ $refundPolicy }} How to ask and how long it takes are explained in our <a href="{{ route('refund') }}" class="pautan">refund policy</a>.</p>
    </section>

    <section>
        <h2>7. Use of content</h2>
        <ul>
            <li>Content is for your family's personal reading only.</li>
            <li>You may not copy, download without permission, distribute, or share account access with people outside your family.</li>
            <li>Copyright in all content belongs to {{ $companyName }} or its licensors.</li>
        </ul>
    </section>

    <section>
        <h2>8. Changes and service interruptions</h2>
        <p>
            We may add, change or remove book titles from time to time. We do our best to keep the platform available,
            but brief interruptions for maintenance may happen.
        </p>
    </section>

    <section>
        <h2>9. Account termination</h2>
        <p>We may suspend accounts that break these terms, for example by sharing an account widely or distributing content.</p>
    </section>

    <section>
        <h2>10. Law</h2>
        <p>These terms are governed by the laws of Malaysia.</p>
    </section>

    <section>
        <h2>11. Contact us</h2>
        <p>
            For any questions,
            @if ($contactEmail)
                e-mail <a href="mailto:{{ $contactEmail }}" class="pautan">{{ $contactEmail }}</a>.
            @else
                contact us at the e-mail address shown on your receipt.
            @endif
            See also our <a href="{{ route('privacy') }}" class="pautan">privacy notice</a>.
        </p>
    </section>
</x-legal-page>
