@props(['book', 'rank' => null])

{{-- A book in a shelf row: cover, optional "#1 Paling popular" badge, title, author, what it costs. --}}
<a href="{{ route('catalog.show', $book) }}" {{ $attributes->class(['group block']) }}>
    <div class="relative">
        <x-book-cover :book="$book" class="transition group-hover:-translate-y-1" />
        @if ($rank)
            <span class="absolute top-2 left-2 rounded-full border-2 border-ink bg-kuning px-2 py-0.5 text-xs font-extrabold shadow-[0_2px_0_var(--color-ink)]">
                {{ $rank === 1 ? '🏆' : '🔥' }} #{{ $rank }}
            </span>
        @endif
    </div>
    <p class="mt-2 font-display leading-tight font-bold group-hover:text-plum">{{ $book->title }}</p>
    <p class="text-sm text-ink/70">{{ $book->author }}</p>
    <p class="mt-1 text-xs font-bold">
        @if ($book->single_price_sen)
            <span class="text-magenta">{{ App\Support\Money::short($book->single_price_sen) }}</span>
            <span class="text-ink/60">atau dalam Pas</span>
        @else
            <span class="text-biru">Dalam Pas Tahunan</span>
        @endif
    </p>
</a>
