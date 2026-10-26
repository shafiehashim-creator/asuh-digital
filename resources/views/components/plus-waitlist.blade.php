@props(['opensOn' => null, 'dark' => false])

{{--
    Langganan Plus shown but not sold yet: when it opens, a "Beritahu saya"
    e-mail sign-up, and the yearly subscription to start with meanwhile.
--}}
<div id="beritahu" {{ $attributes->class(['mx-auto max-w-xl scroll-mt-24 rounded-3xl border-2 border-ink bg-white p-6 text-left text-ink shadow-[0_4px_0_var(--color-ink)]']) }}>
    <p class="inline-block rounded-full border-2 border-ink bg-oren px-3 py-1 text-sm font-extrabold">{{ __('Akan dibuka') }}</p>
    <h2 class="mt-3 font-display text-2xl font-extrabold text-plum">
        @if ($opensOn)
            {{ __('Langganan Plus dibuka pada :date', ['date' => $opensOn->translatedFormat('j F Y')]) }}
        @else
            {{ __('Langganan Plus dibuka tidak lama lagi') }}
        @endif
    </h2>

    @if (session('plus_waitlist'))
        <p class="mt-3 rounded-2xl bg-lavender p-4 font-bold" role="status">✓ {{ session('plus_waitlist') }}</p>
    @else
        <p class="mt-2 text-ink/80">{{ __('Tinggalkan e-mel anda. Kami akan beritahu anda sebaik ia dibuka.') }}</p>
        <form method="POST" action="{{ route('plus.waitlist') }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
            @csrf
            <label for="waitlist-email" class="sr-only">{{ __('E-mel') }}</label>
            <input id="waitlist-email" type="email" name="email" required autocomplete="email"
                   value="{{ old('email', auth()->user()?->email) }}" placeholder="{{ __('E-mel anda') }}"
                   class="min-h-11 flex-1 rounded-full border-2 border-ink px-4">
            <button type="submit" class="btn-utama">{{ __('Beritahu saya') }}</button>
        </form>
        @error('email')
            <p class="mt-2 text-sm font-bold text-magenta">{{ $message }}</p>
        @enderror
    @endif

    <div class="mt-6 border-t-2 border-ink/10 pt-5">
        <p class="font-bold">{{ __('Sementara itu, mulakan dengan Langganan Tahunan.') }}</p>
        <p class="mt-1 text-sm text-ink/75">{{ __('Bila Plus dibuka, naik taraf dan bayar beza harga untuk baki hari sahaja.') }}</p>
        <a href="{{ route('pass.show') }}" class="btn-gelap mt-4">{{ __('Langgan Tahunan sekarang') }} &rarr;</a>
    </div>
</div>
