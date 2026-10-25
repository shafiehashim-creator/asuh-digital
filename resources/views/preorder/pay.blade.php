@php
    use App\Enums\PaymentMethod;
    use App\Models\Setting;
    use App\Support\Money;

    $user = auth()->user();
    $chipUrl = Route::has('payment.chip.start') ? route('payment.chip.start', $order) : null;
    $receiptUrl = Route::has('preorder.receipt') ? route('preorder.receipt', $order) : null;
    $bank = [
        'Bank' => Setting::get('bank_name'),
        'Nama akaun' => Setting::get('bank_account_name'),
        'No. akaun' => Setting::get('bank_account_number'),
    ];
    $cardClass = 'rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)] sm:p-8';
@endphp

<x-layouts.app :title="'Pesanan '.$order->reference">
    <div class="mx-auto max-w-2xl space-y-6 px-4 py-10">
        <div>
            <p class="text-sm font-bold text-ink/70">Pesanan {{ $order->reference }}</p>
            <h1 class="font-display text-3xl font-extrabold text-magenta sm:text-4xl">
                @if ($order->isPaid())
                    Bayaran diterima. Terima kasih!
                @elseif ($awaitingConfirmation)
                    Resit diterima
                @elseif ($isExpired)
                    Tempahan telah tamat tempoh
                @else
                    Satu langkah lagi
                @endif
            </h1>
        </div>

        @error('payment')
            <div role="alert" class="rounded-xl border-2 border-magenta bg-magenta/5 px-4 py-3 font-semibold text-magenta">{{ $message }}</div>
        @enderror

        @if (session('payment_checking') && ! $order->isPaid())
            {{-- Reload until the signed CHIP callback has marked the order as paid. --}}
            <meta http-equiv="refresh" content="5;url={{ route('preorder.pay', $order) }}?semak=1">
            <div role="status" class="rounded-xl border-2 border-biru/30 bg-biru/5 px-4 py-3 font-semibold text-biru">
                Kami sedang mengesahkan bayaran anda dengan CHIP. Halaman ini akan dikemas kini sendiri dalam beberapa saat.
            </div>
        @endif

        @unless ($user->hasVerifiedEmail())
            <div role="status" class="rounded-xl border-2 border-biru/30 bg-biru/5 px-4 py-3 text-sm">
                Kami telah menghantar pautan pengesahan ke <strong>{{ $user->email }}</strong>. Sahkan e-mel anda untuk membuka halaman Akaun saya.
            </div>
        @endunless

        {{-- Summary --}}
        <section class="{{ $cardClass }}" aria-label="Ringkasan pesanan">
            <dl class="space-y-2">
                <div class="flex justify-between gap-4"><dt class="text-ink/80">Item</dt><dd class="font-bold">{{ $order->itemName() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink/80">Kaedah</dt><dd class="font-bold">{{ $order->payment_method->getLabel() }}</dd></div>
                @if ($order->discount_sen > 0)
                    <div class="flex justify-between gap-4"><dt class="text-ink/70">Diskaun {{ $order->referralInstitution?->name ?? 'sekolah' }}</dt><dd class="font-semibold text-biru">−{{ Money::format($order->discount_sen) }}</dd></div>
                @endif
                <div class="flex justify-between gap-4 border-t-2 border-ink/10 pt-2"><dt class="font-bold">Jumlah</dt><dd class="font-display text-2xl font-extrabold">{{ Money::format($order->amount_sen) }}</dd></div>
            </dl>
        </section>

        @if ($order->isPaid())
            <section class="{{ $cardClass }}">
                @if ($order->book)
                    <p>Resit telah dihantar ke e-mel anda. <strong>{{ $order->book->title }}</strong> kini terbuka dalam akaun anda.</p>
                @else
                    <p>Resit telah dihantar ke e-mel anda. Status Langganan Tahunan boleh dilihat di halaman Akaun saya.</p>
                @endif
                <a href="{{ route('account') }}" class="btn-utama mt-4">Ke Akaun saya</a>
            </section>
        @elseif ($awaitingConfirmation)
            <section class="{{ $cardClass }}">
                @if (session('receipt_uploaded'))
                    <p role="status" class="mb-3 font-semibold text-biru">Terima kasih, resit anda telah dihantar.</p>
                @endif
                <p>Kami akan menyemak pindahan anda dan mengesahkan bayaran secepat mungkin. Anda akan menerima resit melalui e-mel sebaik sahaja {{ $order->book ? 'buku ini dibuka' : 'Langganan Tahunan diaktifkan' }}.@if ($preorder) Slot anda kekal ditempah sepanjang semakan.@endif</p>
                @if ($canUploadReceipt && $receiptUrl)
                    <a href="{{ $receiptUrl }}" class="pautan mt-4 inline-block">Tersalah resit? Hantar semula</a>
                @endif
            </section>
        @elseif ($isExpired)
            <section class="{{ $cardClass }} space-y-4">
                <p>Slot yang ditempah untuk pesanan ini telah dilepaskan kerana bayaran tidak diterima dalam masa yang ditetapkan.</p>
                @if ($canUploadReceipt && $receiptUrl)
                    <p>Jika anda <strong>sudah membuat pindahan</strong>, muat naik resit dan kami akan menyemaknya.</p>
                    <a href="{{ $receiptUrl }}" class="btn-utama">Muat naik resit</a>
                @endif
                <a href="{{ $retryUrl }}" @class(['btn-gelap' => $canUploadReceipt, 'btn-utama' => ! $canUploadReceipt])>Tempah semula</a>
            </section>
        @elseif ($order->payment_method === PaymentMethod::Chip)
            <section class="{{ $cardClass }}">
                @if ($preorder)
                    <p>Slot anda ditempah sehingga <strong>{{ $preorder->reserved_until->translatedFormat('g:i A, j F Y') }}</strong>. Selesaikan bayaran sebelum masa itu.</p>
                @endif
                @if ($chipUrl)
                    <form method="POST" action="{{ $chipUrl }}" class="mt-5">
                        @csrf
                        <button type="submit" class="btn-utama w-full text-lg">Bayar {{ Money::format($order->amount_sen) }} sekarang</button>
                    </form>
                    <p class="mt-3 text-center text-sm text-ink/70">Anda akan dibawa ke halaman bayaran selamat CHIP (FPX, kad atau e-wallet).</p>
                @else
                    <p class="mt-4 rounded-xl bg-oren/15 px-4 py-3 text-sm font-semibold">Bayaran dalam talian belum dibuka. Sila cuba sebentar lagi.</p>
                @endif
            </section>
        @else
            <section class="{{ $cardClass }} space-y-4">
                @if ($preorder)
                    <p>Slot anda ditempah sehingga <strong>{{ $preorder->reserved_until->translatedFormat('g:i A, j F Y') }}</strong>. Pindahkan bayaran dan muat naik resit sebelum masa itu.</p>
                @else
                    <p>Pindahkan bayaran ke akaun di bawah, kemudian muat naik resit.</p>
                @endif

                @if (array_filter($bank))
                    <dl class="space-y-1 rounded-2xl bg-lavender p-4">
                        @foreach ($bank as $label => $value)
                            <div class="flex justify-between gap-4"><dt class="text-ink/80">{{ $label }}</dt><dd class="font-bold">{{ $value ?? '-' }}</dd></div>
                        @endforeach
                        <div class="flex justify-between gap-4"><dt class="text-ink/80">Rujukan</dt><dd class="font-bold">{{ $order->reference }}</dd></div>
                    </dl>
                    <p class="text-sm text-ink/80">Sila tulis rujukan <strong>{{ $order->reference }}</strong> dalam butiran pindahan.</p>
                @else
                    <p class="rounded-xl bg-oren/15 px-4 py-3 text-sm font-semibold">Butiran akaun bank akan dipaparkan di sini. Sila hubungi kami jika anda perlukannya sekarang.</p>
                @endif

                @if ($receiptUrl)
                    <a href="{{ $receiptUrl }}" class="btn-utama w-full">Muat naik resit</a>
                @endif
            </section>
        @endif
    </div>
</x-layouts.app>
