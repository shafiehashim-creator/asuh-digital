{{-- BM | EN: the current language is marked; the other is a link that switches. --}}
<nav aria-label="{{ __('Bahasa') }}" {{ $attributes->class(['flex items-center rounded-full border-2 border-white/40 p-0.5 text-xs font-extrabold']) }}>
    @foreach (App\Support\Locale::SUPPORTED as $code => $label)
        @if (app()->getLocale() === $code)
            <span aria-current="true" class="inline-flex min-h-9 min-w-10 items-center justify-center rounded-full bg-kuning px-2 text-ink" lang="{{ $code }}">{{ $label }}</span>
        @else
            <a href="{{ route('locale.switch', $code) }}" lang="{{ $code }}" hreflang="{{ $code }}"
                class="inline-flex min-h-9 min-w-10 items-center justify-center rounded-full px-2 text-white hover:bg-white/10"
                aria-label="{{ $code === 'en' ? 'English' : 'Bahasa Melayu' }}">{{ $label }}</a>
        @endif
    @endforeach
</nav>
