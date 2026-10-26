@php use App\Support\Money; @endphp

<x-auth-card title="Muat naik resit" :intro="__('Pesanan :reference', ['reference' => $order->reference]).' · '.Money::format($order->totalSen())">
    <form method="POST" action="{{ route('preorder.receipt.store', $order) }}" enctype="multipart/form-data" class="space-y-5">
        @csrf

        <div>
            <label for="receipt" class="label">{{ __('Resit pindahan bank') }}</label>
            <input id="receipt" name="receipt" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required
                @class(['input file:mr-3 file:rounded-full file:border-0 file:bg-lavender file:px-4 file:py-1.5 file:font-bold', 'border-magenta!' => $errors->has('receipt')])>
            @error('receipt')
                <p class="mt-1 text-sm font-semibold text-magenta">{{ $message }}</p>
            @else
                <p class="mt-1 text-sm text-ink/70">{{ __('Gambar (JPG, PNG) atau PDF, maksimum 5 MB. Pastikan tarikh, jumlah dan rujukan jelas.') }}</p>
            @enderror
        </div>

        <button type="submit" class="btn-utama w-full">{{ __('Hantar resit') }}</button>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('preorder.pay', $order) }}" class="pautan">{{ __('Kembali ke pesanan') }}</a>
    </p>
</x-auth-card>
