@php
    use App\Enums\PaymentMethod;
    use App\Models\Setting;
    use App\Support\Money;

    $user = auth()->user();
    $chipUrl = Route::has('payment.chip.start') ? route('payment.chip.start', $order) : null;
    $receiptUrl = Route::has('preorder.receipt') ? route('preorder.receipt', $order) : null;
    $bank = [
        __('Bank') => Setting::get('bank_name'),
        __('Nama akaun') => Setting::get('bank_account_name'),
        __('No. akaun') => Setting::get('bank_account_number'),
    ];
    $cardClass = 'rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)] sm:p-8';
@endphp

<x-layouts.app :title="__('Pesanan :reference', ['reference' => $order->reference])">
    <div class="mx-auto max-w-2xl space-y-6 px-4 py-10">
        <div>
            <p class="text-sm font-bold text-ink/70">{{ __('Pesanan :reference', ['reference' => $order->reference]) }}</p>
            <h1 class="font-display text-3xl font-extrabold text-magenta sm:text-4xl">
                @if ($order->isPaid())
                    {{ __('Bayaran diterima. Terima kasih!') }}
                @elseif ($awaitingConfirmation)
                    {{ __('Resit diterima') }}
                @elseif ($isExpired)
                    {{ __('Tempahan telah tamat tempoh') }}
                @else
                    {{ __('Satu langkah lagi') }}
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
                {{ __('Kami sedang mengesahkan bayaran anda dengan CHIP. Halaman ini akan dikemas kini sendiri dalam beberapa saat.') }}
            </div>
        @endif

        @unless ($user->hasVerifiedEmail())
            <div role="status" class="rounded-xl border-2 border-biru/30 bg-biru/5 px-4 py-3 text-sm">
                {!! __('Kami telah menghantar pautan pengesahan ke :email. Sahkan e-mel anda untuk membuka halaman Akaun saya.', ['email' => '<strong>'.e($user->email).'</strong>']) !!}
            </div>
        @endunless

        {{-- Summary --}}
        <section class="{{ $cardClass }}" aria-label="{{ __('Ringkasan pesanan') }}">
            <dl class="space-y-2">
                <div class="flex justify-between gap-4"><dt class="text-ink/80">{{ __('Item') }}</dt><dd class="font-bold">{{ $order->itemName() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink/80">{{ __('Kaedah') }}</dt><dd class="font-bold">{{ $order->payment_method->getLabel() }}</dd></div>
                @if ($order->discount_sen > 0)
                    <div class="flex justify-between gap-4"><dt class="text-ink/70">{{ $order->discountLabel() }}</dt><dd class="font-semibold text-biru">−{{ Money::format($order->discount_sen) }}</dd></div>
                @endif
                @if ($order->processing_fee_sen > 0)
                    <div class="flex justify-between gap-4"><dt class="text-ink/70">{{ __('Caj pemprosesan (:method)', ['method' => $order->chip_method?->label()]) }}</dt><dd class="font-semibold">{{ Money::format($order->processing_fee_sen) }}</dd></div>
                @endif
                <div class="flex justify-between gap-4 border-t-2 border-ink/10 pt-2"><dt class="font-bold">{{ __('Jumlah') }}</dt><dd class="font-display text-2xl font-extrabold">{{ Money::format($order->totalSen()) }}</dd></div>
            </dl>
        </section>

        @if ($order->isPaid())
            <section class="{{ $cardClass }}">
                @if ($order->book)
                    <p>{!! __('Resit telah dihantar ke e-mel anda. :book kini terbuka dalam akaun anda.', ['book' => '<strong>'.e($order->book->localTitle()).'</strong>']) !!}</p>
                @else
                    <p>{{ __('Resit telah dihantar ke e-mel anda. Status Langganan Tahunan boleh dilihat di halaman Akaun saya.') }}</p>
                @endif
                <a href="{{ route('account') }}" class="btn-utama mt-4">{{ __('Ke Akaun saya') }}</a>
            </section>
        @elseif ($awaitingConfirmation)
            <section class="{{ $cardClass }}">
                @if (session('receipt_uploaded'))
                    <p role="status" class="mb-3 font-semibold text-biru">{{ __('Terima kasih, resit anda telah dihantar.') }}</p>
                @endif
                <p>{{ $order->book ? __('Kami akan menyemak pindahan anda dan mengesahkan bayaran secepat mungkin. Anda akan menerima resit melalui e-mel sebaik sahaja buku ini dibuka.') : __('Kami akan menyemak pindahan anda dan mengesahkan bayaran secepat mungkin. Anda akan menerima resit melalui e-mel sebaik sahaja Langganan Tahunan diaktifkan.') }}@if ($preorder) {{ __('Slot anda kekal ditempah sepanjang semakan.') }}@endif</p>
                @if ($canUploadReceipt && $receiptUrl)
                    <a href="{{ $receiptUrl }}" class="pautan mt-4 inline-block">{{ __('Tersalah resit? Hantar semula') }}</a>
                @endif
            </section>
        @elseif ($isExpired)
            <section class="{{ $cardClass }} space-y-4">
                <p>{{ __('Slot yang ditempah untuk pesanan ini telah dilepaskan kerana bayaran tidak diterima dalam masa yang ditetapkan.') }}</p>
                @if ($canUploadReceipt && $receiptUrl)
                    <p>{!! __('Jika anda :done, muat naik resit dan kami akan menyemaknya.', ['done' => '<strong>'.e(__('sudah membuat pindahan')).'</strong>']) !!}</p>
                    <a href="{{ $receiptUrl }}" class="btn-utama">{{ __('Muat naik resit') }}</a>
                @endif
                <a href="{{ $retryUrl }}" @class(['btn-gelap' => $canUploadReceipt, 'btn-utama' => ! $canUploadReceipt])>{{ __('Tempah semula') }}</a>
            </section>
        @elseif ($order->payment_method === PaymentMethod::Chip)
            <section class="{{ $cardClass }}">
                @if ($preorder)
                    <p>{!! __('Slot anda ditempah sehingga :time. Selesaikan bayaran sebelum masa itu.', ['time' => '<strong>'.e($preorder->reserved_until->translatedFormat('g:i A, j F Y')).'</strong>']) !!}</p>
                @endif
                @if ($chipUrl && $chipOptions->isNotEmpty())
                    <h2 class="mt-5 font-display text-lg font-bold text-plum first:mt-0">{{ __('Pilih kaedah bayaran') }}</h2>
                    <ul class="mt-3 space-y-3">
                        @foreach ($chipOptions as ['method' => $method, 'fee' => $fee])
                            <li>
                                <form method="POST" action="{{ $chipUrl }}">
                                    @csrf
                                    <input type="hidden" name="kaedah" value="{{ $method->value }}">
                                    <button type="submit" class="flex min-h-16 w-full items-center justify-between gap-4 rounded-2xl border-2 border-ink bg-white px-4 py-3 text-left shadow-[0_3px_0_var(--color-ink)] transition hover:-translate-y-px hover:bg-kuning/30 active:translate-y-0.5 active:shadow-[0_1px_0_var(--color-ink)]">
                                        <span>
                                            <span class="block font-display text-lg font-bold">{{ $method->label() }}</span>
                                            <span class="block text-sm text-ink/70">{{ $fee > 0 ? __('Termasuk caj pemprosesan :fee', ['fee' => Money::format($fee)]) : __('Tiada caj pemprosesan') }}</span>
                                        </span>
                                        <span class="shrink-0 font-display text-xl font-extrabold">{{ Money::format($order->amount_sen + $fee) }}</span>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-center text-sm text-ink/70">{{ __('Anda akan dibawa ke halaman bayaran selamat CHIP. Caj pemprosesan dikenakan oleh penyedia bayaran.') }}</p>
                @else
                    <p class="mt-4 rounded-xl bg-oren/15 px-4 py-3 text-sm font-semibold">{{ __('Bayaran dalam talian belum dibuka. Sila cuba sebentar lagi.') }}</p>
                @endif
            </section>
        @else
            <section class="{{ $cardClass }} space-y-4">
                @if ($preorder)
                    <p>{!! __('Slot anda ditempah sehingga :time. Pindahkan bayaran dan muat naik resit sebelum masa itu.', ['time' => '<strong>'.e($preorder->reserved_until->translatedFormat('g:i A, j F Y')).'</strong>']) !!}</p>
                @else
                    <p>{{ __('Pindahkan bayaran ke akaun di bawah, kemudian muat naik resit.') }}</p>
                @endif

                @if (array_filter($bank))
                    <dl class="space-y-1 rounded-2xl bg-lavender p-4">
                        @foreach ($bank as $label => $value)
                            <div class="flex justify-between gap-4"><dt class="text-ink/80">{{ $label }}</dt><dd class="font-bold">{{ $value ?? '-' }}</dd></div>
                        @endforeach
                        <div class="flex justify-between gap-4"><dt class="text-ink/80">{{ __('Rujukan') }}</dt><dd class="font-bold">{{ $order->reference }}</dd></div>
                    </dl>
                    <p class="text-sm text-ink/80">{!! __('Sila tulis rujukan :reference dalam butiran pindahan.', ['reference' => '<strong>'.e($order->reference).'</strong>']) !!}</p>
                @else
                    <p class="rounded-xl bg-oren/15 px-4 py-3 text-sm font-semibold">{{ __('Butiran akaun bank akan dipaparkan di sini. Sila hubungi kami jika anda perlukannya sekarang.') }}</p>
                @endif

                @if ($receiptUrl)
                    <a href="{{ $receiptUrl }}" class="btn-utama w-full">{{ __('Muat naik resit') }}</a>
                @endif
            </section>
        @endif
    </div>
</x-layouts.app>
