@props(['series', 'books'])

{{-- A series in the catalogue: its first cover with the next ones peeking out behind. --}}
@php($first = $books->first())
<a href="{{ route('series.show', $series) }}" class="group block rounded-2xl focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-biru">
    <div class="relative mr-3 mt-3">
        @foreach ($books->slice(1, 2)->reverse() as $i => $behind)
            <div class="absolute inset-0 overflow-hidden rounded-2xl border-2 border-ink bg-lavender" style="transform: translate({{ ($loop->remaining + 1) * 6 }}px, -{{ ($loop->remaining + 1) * 6 }}px)" aria-hidden="true">
                @if ($behind->cover_path)
                    <img src="{{ Storage::disk('public')->url($behind->cover_path) }}" alt="" loading="lazy" class="h-full w-full object-cover opacity-70">
                @endif
            </div>
        @endforeach
        <x-book-cover :book="$first" class="relative transition group-hover:-translate-y-1" />
        <span class="absolute top-2 right-2 rounded-full border-2 border-ink bg-kuning px-2 py-0.5 text-xs font-extrabold">📚 {{ __(':count buku', ['count' => $books->count()]) }}</span>
    </div>
    <p class="mt-3 text-xs font-extrabold tracking-wide text-biru uppercase">{{ __('Siri') }}</p>
    <p class="font-display text-lg leading-tight font-bold group-hover:text-plum">{{ $series->localName() }}</p>
    <p class="mt-1 flex flex-wrap gap-1 text-xs font-bold">
        <span class="rounded-full bg-lavender px-2 py-0.5">{{ $first->age_group->getLabel() }}</span>
        @foreach ($first->categories as $category)
            <span class="rounded-full bg-biru/10 px-2 py-0.5 text-biru">{{ __($category->name) }}</span>
        @endforeach
    </p>
</a>
