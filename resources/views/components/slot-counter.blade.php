@props(['limit', 'remaining'])

@php
    $taken = $limit - $remaining;
    $percent = $limit > 0 ? (int) round($taken / $limit * 100) : 100;
    // The last fifth of the slots (at least 10) are shown in red as almost gone.
    $almostGone = $remaining > 0 && $taken > 0 && $remaining <= max(10, (int) ceil($limit / 5));
@endphp

<div {{ $attributes->class(['rounded-2xl border-2 border-magenta bg-magenta/5 p-3' => $almostGone]) }}>
    @if ($almostGone)
        <p class="font-display text-lg font-extrabold text-magenta">🔥 {{ __('Hampir habis! Tinggal :count slot sahaja', ['count' => $remaining]) }}</p>
    @endif
    <div class="flex items-baseline justify-between gap-2 text-sm font-bold">
        @if ($remaining === 0)
            <span class="text-magenta">{{ __('Semua :count slot telah ditempah', ['count' => $limit]) }}</span>
        @elseif ($taken === 0)
            {{-- Before the first sale, "0 of 100 sold" would discourage buyers. --}}
            <span>{!! __('Terhad kepada :families pertama', ['families' => '<span class="text-magenta">'.e(__(':count keluarga', ['count' => $limit])).'</span>']) !!}</span>
        @else
            <span>{!! __(':taken keluarga sudah menempah · tinggal :remaining slot', ['taken' => '<span class="text-magenta">'.$taken.'</span>', 'remaining' => '<span class="text-magenta">'.$remaining.'</span>']) !!}</span>
        @endif
    </div>
    <div class="mt-2 h-3 overflow-hidden rounded-full border-2 border-ink bg-white"
        role="progressbar" aria-label="{{ __('Slot yang telah ditempah') }}" aria-valuemin="0" aria-valuemax="{{ $limit }}" aria-valuenow="{{ $taken }}">
        <div @class(['h-full', 'bg-magenta animate-pulse' => $almostGone, 'bg-biru' => ! $almostGone]) style="width: {{ $percent }}%"></div>
    </div>
</div>
