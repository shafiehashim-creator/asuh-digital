@props(['book', 'size' => 'md'])

@php
    // A simple coloured card stands in until a cover is uploaded.
    $palette = ['bg-plum text-white', 'bg-magenta text-white', 'bg-biru text-white', 'bg-oren text-ink', 'bg-kuning text-ink'];
    $placeholder = $palette[$book->id % count($palette)];
@endphp

<div {{ $attributes->class(['aspect-[3/4] w-full overflow-hidden rounded-2xl border-2 border-ink shadow-[0_3px_0_var(--color-ink)]']) }}>
    @if ($book->cover_path)
        <img src="{{ Storage::disk('public')->url($book->cover_path) }}" alt="{{ __('Kaver :title', ['title' => $book->localTitle()]) }}"
            loading="lazy" class="h-full w-full object-cover">
    @elseif ($size === 'sm')
        {{-- Too small for the title; it is printed beside the cover. --}}
        <div class="h-full w-full {{ $placeholder }}"></div>
    @else
        <div class="flex h-full w-full flex-col justify-between p-4 {{ $placeholder }}">
            <span class="font-display text-xs font-bold tracking-wide uppercase opacity-80">ASUH</span>
            <span @class(['font-display font-extrabold leading-tight', 'text-xl' => $size === 'md', 'text-3xl' => $size === 'lg'])>{{ $book->localTitle() }}</span>
        </div>
    @endif
</div>
