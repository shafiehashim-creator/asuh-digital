@props(['limit', 'remaining'])

@php
    $taken = $limit - $remaining;
    $percent = $limit > 0 ? (int) round($taken / $limit * 100) : 100;
@endphp

<div {{ $attributes }}>
    <div class="flex items-baseline justify-between gap-2 text-sm font-bold">
        @if ($remaining === 0)
            <span class="text-magenta">Semua {{ $limit }} slot telah ditempah</span>
        @elseif ($taken === 0)
            {{-- Before the first sale, "0 of 100 sold" would discourage buyers. --}}
            <span>Terhad kepada <span class="text-magenta">{{ $limit }} keluarga</span> pertama</span>
        @else
            <span>Tinggal <span class="text-magenta">{{ $remaining }}</span> daripada {{ $limit }} slot</span>
        @endif
    </div>
    <div class="mt-2 h-3 overflow-hidden rounded-full border-2 border-ink bg-white"
        role="progressbar" aria-label="Slot yang telah ditempah" aria-valuemin="0" aria-valuemax="{{ $limit }}" aria-valuenow="{{ $taken }}">
        <div class="h-full bg-biru" style="width: {{ $percent }}%"></div>
    </div>
</div>
