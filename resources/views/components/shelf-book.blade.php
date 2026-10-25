@props(['book'])

{{-- A book on the family's own shelf: the cover opens the reader straight away. --}}
<a href="{{ route('reader.show', $book) }}" {{ $attributes->class(['group block']) }}>
    <x-book-cover :book="$book" class="transition group-hover:-translate-y-1" />
    <p class="mt-2 font-display leading-tight font-bold group-hover:text-plum">{{ $book->title }}</p>
    <span class="mt-1 inline-flex min-h-11 items-center text-sm font-bold text-biru underline-offset-2 group-hover:underline">Baca</span>
</a>
