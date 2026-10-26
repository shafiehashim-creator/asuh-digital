<x-legal-page title="Privacy notice" updated="26 October 2026">
    <section>
        <h2>1. Who we are</h2>
        <p>
            ASUH Digital is operated by <strong>{{ $companyName }}</strong>@if ($companySsm) ({{ $companySsm }})@endif.
            This notice explains how we collect and use your personal data in line with the
            Personal Data Protection Act 2010 (PDPA). If this English version and the Bahasa Melayu version differ,
            the Bahasa Melayu version prevails.
        </p>
    </section>

    <section>
        <h2>2. Data we collect</h2>
        <p>From parents or guardians who register:</p>
        <ul>
            <li>Name, e-mail address and phone number.</li>
            <li>Account password, stored in encrypted (hashed) form. We cannot see it.</li>
            <li>State, number of children and their age groups (6–9 or 10–13).</li>
            <li>Order and payment records, and bank transfer receipts if you choose that method.</li>
        </ul>
        <p>
            About children: accounts belong to parents. Children have no e-mail address or password of their own.
            We do <strong>not</strong> collect a child's full name, photo, date of birth or school. A child profile only
            keeps a nickname, an age group and the drawn avatar chosen.
        </p>
        <p>
            When a child reads in Kids Mode, we record minutes read, the last page, the books read, quiz results and
            badges earned, and minutes of animated video watched (for the Plus Subscription). These records are used to
            continue reading, award badges and send parents a weekly reading report (which can be stopped at any time).
            Parents can delete a child profile on the My account page, and all of that child's reading records are deleted with it.
        </p>
        <p>
            For institutions (kindergartens, schools and tahfiz centres) with a licence, teachers register pupils by
            nickname only. Pupils sign in with a class code, without an e-mail address or password. Pupils' reading,
            quiz and badge records are shown to their class teacher and the institution's administrator, and are deleted
            when the teacher deletes the pupil or the class.
        </p>
        <p>
            To understand how the site is used, we record the pages visited, the type of device (phone, tablet or
            computer) and the site or campaign that brought you here. These records use a random number in your browser
            session, <strong>without</strong> your IP address and without third-party cookies, and are deleted after about a year.
        </p>
        <p>
            Your card or online banking details are entered directly on CHIP's payment page and are
            <strong>not</strong> stored by us.
        </p>
    </section>

    <section>
        <h2>3. Why we use it</h2>
        <ul>
            <li>To create and manage your account.</li>
            <li>To process orders and payments, and send receipts.</li>
            <li>To send important e-mails about your account, your subscription and the platform launch.</li>
            <li>To understand our readers' age groups so we provide suitable books.</li>
            <li>To meet legal requirements, including accounting records.</li>
        </ul>
        <p>We do not sell your data, and we do not use it for third-party advertising.</p>
    </section>

    <section>
        <h2>4. Who receives the data</h2>
        <ul>
            <li><strong>CHIP</strong> (our payment gateway), to process online payments.</li>
            <li>Our server hosting and e-mail providers, who store and send data on our behalf.</li>
            <li>The authorities, where required by law.</li>
        </ul>
    </section>

    <section>
        <h2>5. Cookies</h2>
        <p>We only use our own cookies:</p>
        <ul>
            <li>A sign-in session cookie and a form security cookie, which the site needs to work.</li>
            <li>A language cookie (BM or English), kept for a year.</li>
            <li>An affiliate referral cookie: if you open an ASUH affiliate partner's link, the partner's code is kept for a set period (usually 30 days) so that your purchase is credited to them. Affiliate partners only see the type of product bought and the date, never your name or details.</li>
        </ul>
        <p>We do not use third-party analytics or advertising cookies.</p>
    </section>

    <section>
        <h2>6. How long we keep it</h2>
        <p>
            Account data is kept while your account is active. Order and payment records are kept for the period
            required by accounting and tax law, even if the account is deleted.
        </p>
    </section>

    <section>
        <h2>7. Your rights</h2>
        <p>You have the right to:</p>
        <ul>
            <li>Get a copy of your personal data.</li>
            <li>Correct inaccurate data. You can update your name, e-mail and phone yourself on the My account page.</li>
            <li>Withdraw your consent and ask for your account and your children's data to be deleted.</li>
        </ul>
        <p>
            {{-- Keep a space before Blade directives: "word@if" is not parsed as a directive. --}}
            To make a request,
            @if ($contactEmail)
                e-mail us at <a href="mailto:{{ $contactEmail }}" class="pautan">{{ $contactEmail }}</a>.
            @else
                contact us at the e-mail address shown on your receipt.
            @endif
            We will reply within 21 days.
        </p>
    </section>

    <section>
        <h2>8. Security</h2>
        <p>
            Connections to this site are encrypted (HTTPS). Passwords are stored hashed, and bank transfer receipts are
            kept in a private area of the server that only administrators can open.
        </p>
    </section>

    <section>
        <h2>9. Changes to this notice</h2>
        <p>We will tell you about important changes by e-mail or on this site.</p>
    </section>

    @if ($companyAddress || $contactEmail)
        <section>
            <h2>10. Contact us</h2>
            <p>
                {{ $companyName }}<br>
                @if ($companyAddress){!! nl2br(e($companyAddress)) !!}<br>@endif
                @if ($contactEmail)<a href="mailto:{{ $contactEmail }}" class="pautan">{{ $contactEmail }}</a>@endif
            </p>
        </section>
    @endif
</x-legal-page>
