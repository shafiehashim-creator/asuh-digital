{{--
    Thin campaign bar under the header on every public page (inside the
    sticky header on phones, so it stays in view): title, the countdown,
    the promo code and a link. Before the campaign starts it shows "Akan
    datang" and counts to the start. The visitor can close it; it stays
    closed for that campaign (until the admin edits it).
--}}
@php
    $campaigns = app(App\Services\Campaigns::class);
    $campaign = $campaigns->bar();
@endphp

@if ($campaign)
    @php
        $soon = $campaign->isUpcoming();
        $seconds = $campaign->secondsLeft();
        $prices = $campaigns->prices($campaign);
        $key = 'asuh-campaign-'.$campaign->id.'-'.$campaign->updated_at?->timestamp;
        $days = intdiv($seconds, 86400);
        $clock = sprintf('%02d:%02d:%02d', intdiv($seconds % 86400, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    @endphp
    <div x-data="{ closed: (() => { try { return localStorage.getItem('{{ $key }}') === '1' } catch (e) { return false } })() }"
        x-show="! closed" data-campaign-bar @class(['text-white', 'bg-biru' => $soon, 'bg-magenta' => ! $soon])>
        <div x-data="countdown({{ $seconds }}, {{ $soon ? 'true' : 'false' }})" x-show="! ended" class="mx-auto flex max-w-6xl items-center gap-2 px-4 py-1.5 text-sm sm:py-2">
            <p class="flex min-w-0 flex-1 flex-wrap items-center justify-center gap-x-3 gap-y-1 text-center">
                <span class="w-full truncate font-extrabold sm:w-auto">
                    @if ($soon)
                        🔜 {{ __('Akan datang') }}: {{ $campaign->localized('title') }}
                    @else
                        🔥 {{ $campaign->localized('title') }}
                    @endif
                    @if ($prices)
                        <span class="hidden sm:inline">· {{ App\Support\Money::short($prices[0]) }}</span>
                    @endif
                </span>
                <span class="whitespace-nowrap">
                    <span class="hidden sm:inline">{{ $soon ? __('Bermula dalam') : __('Tamat dalam') }}</span><span class="sm:hidden" aria-hidden="true">⏳</span>
                    <span class="rounded-md bg-ink/30 px-1.5 py-0.5 font-mono font-bold tabular-nums"
                        x-text="(days > 0 ? days + ' {{ __('hari') }} ' : '') + hours + ':' + minutes + ':' + seconds">{{ $days > 0 ? $days.' '.__('hari').' ' : '' }}{{ $clock }}</span>
                </span>
                {{-- The code shows on bigger screens; on a phone the bar stays two lines. --}}
                @if ($campaign->promo_code && ! $soon)
                    <span class="hidden whitespace-nowrap sm:inline">{{ __('Kod') }} <b class="rounded-md border border-white/60 px-1.5 py-0.5 tracking-wide">{{ $campaign->promo_code }}</b></span>
                @endif
                @if ($campaign->cta_url)
                    <a href="{{ $campaign->cta_url }}" class="font-extrabold whitespace-nowrap text-kuning underline underline-offset-2">{{ $campaign->localized('cta_label') ?: __('Lihat tawaran') }} &rarr;</a>
                @endif
            </p>
            <button type="button" @click="closed = true; try { localStorage.setItem('{{ $key }}', '1') } catch (e) {}"
                class="grid size-9 shrink-0 place-items-center rounded-full hover:bg-white/15" aria-label="{{ __('Tutup') }}">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
            </button>
        </div>
    </div>
@endif
