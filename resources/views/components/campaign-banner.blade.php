{{--
    The campaign's big countdown on the sales pages (home, Langganan
    Tahunan, Langganan Plus): days, hours, minutes and seconds, the campaign
    price, the promo code with a copy button, and the campaign's button.
    Before it starts: "Akan datang", a countdown to the start, and a note
    that buying now is at the normal price.
--}}
@php
    use App\Support\Money;

    $campaigns = app(App\Services\Campaigns::class);
    $campaign = $campaigns->banner();
@endphp

@if ($campaign)
    @php
        $soon = $campaign->isUpcoming();
        $seconds = $campaign->secondsLeft();
        $prices = $campaigns->prices($campaign);
        $parts = [
            [intdiv($seconds, 86400), __('Hari'), 'days'],
            [intdiv($seconds % 86400, 3600), __('Jam'), 'hours'],
            [intdiv($seconds % 3600, 60), __('Minit'), 'minutes'],
            [$seconds % 60, __('Saat'), 'seconds'],
        ];
        $when = fn ($date) => $date->timezone(config('app.timezone'))->translatedFormat('j F Y, g:i A');
    @endphp
    <section x-data="countdown({{ $seconds }}, {{ $soon ? 'true' : 'false' }})" x-show="! ended" data-campaign-banner {{ $attributes->class('mx-auto max-w-6xl px-4') }}>
        <div @class(['grid items-center gap-6 rounded-3xl border-2 border-ink p-6 text-white shadow-[0_6px_0_var(--color-ink)] md:grid-cols-[1fr_auto] md:p-8', 'bg-biru' => $soon, 'bg-plum' => ! $soon])>
            <div>
                <p class="inline-block rounded-full border-2 border-ink bg-oren px-3 py-1 text-xs font-extrabold text-ink uppercase">
                    {{ $soon ? '🔜 '.__('Akan datang') : '⏳ '.__('Tawaran terhad') }}
                </p>
                <h2 class="mt-3 font-display text-3xl leading-tight font-extrabold text-kuning">{{ $campaign->localized('title') }}</h2>
                @if ($prices)
                    <p class="mt-2 font-display text-3xl font-extrabold">
                        {{ Money::short($prices[0]) }}
                        <span class="text-xl text-white/60 line-through">{{ Money::short($prices[1]) }}</span>
                    </p>
                @endif
                @if ($message = $campaign->localized('message'))
                    <p class="mt-2 text-white/90">{{ $message }}</p>
                @endif
                <p class="mt-2 text-sm font-semibold text-white/80">
                    @if ($soon)
                        {{ __('Bermula :date. Anda masih boleh membeli sekarang pada harga biasa.', ['date' => $when($campaign->starts_at)]) }}
                    @else
                        {{ __('Tamat :date', ['date' => $when($campaign->ends_at)]) }}
                    @endif
                </p>
            </div>

            <div class="flex flex-col items-start gap-4 md:items-end">
                <p class="text-sm font-extrabold tracking-wide text-white/85 uppercase">{{ $soon ? __('Bermula dalam') : __('Tamat dalam') }}</p>
                <ol class="-mt-2 flex gap-2" aria-label="{{ __('Masa berbaki') }}">
                    @foreach ($parts as [$value, $label, $key])
                        <li class="grid w-16 place-items-center rounded-2xl border-2 border-ink bg-white py-2 text-ink shadow-[0_3px_0_var(--color-ink)] sm:w-20">
                            <span class="font-display text-3xl font-extrabold tabular-nums sm:text-4xl" x-text="{{ $key }}">{{ $key === 'days' ? $value : sprintf('%02d', $value) }}</span>
                            <span class="text-xs font-bold text-ink/70">{{ $label }}</span>
                        </li>
                    @endforeach
                </ol>
                <div class="flex flex-wrap items-center gap-3">
                    @if ($campaign->promo_code && ! $soon)
                        <button type="button" @click="copy('{{ $campaign->promo_code }}')"
                            class="inline-flex min-h-11 items-center gap-2 rounded-full border-2 border-dashed border-white/70 px-4 font-bold hover:bg-white/10">
                            {{ __('Kod') }} <span class="tracking-wide text-kuning">{{ $campaign->promo_code }}</span>
                            <span class="text-xs text-white/80" x-text="copied ? '{{ __('Disalin!') }}' : '{{ __('Salin') }}'">{{ __('Salin') }}</span>
                        </button>
                    @endif
                    @if ($campaign->cta_url)
                        <a href="{{ $campaign->cta_url }}" class="btn-utama">{{ $campaign->localized('cta_label') ?: __('Lihat tawaran') }} &rarr;</a>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif
