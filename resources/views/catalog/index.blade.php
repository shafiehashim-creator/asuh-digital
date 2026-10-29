@php
    use App\Enums\AgeGroup;

    $filterUrl = fn (array $change) => route('catalog.index', array_filter(array_merge([
        'kategori' => $activeCategory?->slug,
        'umur' => $activeAge?->value,
        'cari' => $search ?: null,
    ], $change)));
    $chip = 'inline-flex min-h-11 items-center rounded-full border-2 px-4 text-sm font-bold';
@endphp

<x-layouts.app :title="__('Katalog buku')">
    <div class="mx-auto max-w-6xl px-4 py-6 sm:py-10">
        <x-catalog-preview-notice />

        <h1 class="font-display text-3xl font-extrabold text-magenta sm:text-4xl">{{ __('Katalog buku') }}</h1>
        <p class="mt-2 text-ink/80">{{ __('Semua buku telah disemak syariah. Pilih ikut umur anak atau kategori.') }}</p>

        <form method="GET" action="{{ route('catalog.index') }}" class="mt-5 flex gap-2" role="search">
            @if ($activeCategory) <input type="hidden" name="kategori" value="{{ $activeCategory->slug }}"> @endif
            @if ($activeAge) <input type="hidden" name="umur" value="{{ $activeAge->value }}"> @endif
            <label for="cari" class="sr-only">{{ __('Cari tajuk atau penulis') }}</label>
            <input id="cari" name="cari" type="search" value="{{ $search }}" placeholder="{{ __('Cari tajuk atau penulis') }}" class="input max-w-md">
            <button type="submit" class="btn-gelap">{{ __('Cari') }}</button>
        </form>

        <nav aria-label="{{ __('Umur') }}" class="chip-row mt-5" x-data="chipRow" :class="more && 'chip-row-more'">
            <a href="{{ $filterUrl(['umur' => null]) }}" @class([$chip, 'border-plum bg-plum text-white' => ! $activeAge, 'border-ink/20 bg-white' => $activeAge]) @if (! $activeAge) aria-current="page" @endif>{{ __('Semua umur') }}</a>
            @foreach ([AgeGroup::SixToNine, AgeGroup::TenToThirteen] as $age)
                <a href="{{ $filterUrl(['umur' => $age->value]) }}" @class([$chip, 'border-plum bg-plum text-white' => $activeAge === $age, 'border-ink/20 bg-white' => $activeAge !== $age]) @if ($activeAge === $age) aria-current="page" @endif>{{ $age->getLabel() }}</a>
            @endforeach
        </nav>

        {{-- On a phone the chosen category is scrolled into view. --}}
        <nav aria-label="{{ __('Kategori') }}" class="chip-row mt-2" x-data="chipRow" :class="more && 'chip-row-more'">
            <a href="{{ $filterUrl(['kategori' => null]) }}" @class([$chip, 'border-biru bg-biru text-white' => ! $activeCategory, 'border-ink/20 bg-white' => $activeCategory])>{{ __('Semua kategori') }}</a>
            @foreach ($categories as $category)
                <a href="{{ $filterUrl(['kategori' => $category->slug]) }}" @class([$chip, 'border-biru bg-biru text-white' => $activeCategory?->is($category), 'border-ink/20 bg-white' => ! $activeCategory?->is($category)]) @if ($activeCategory?->is($category)) aria-current="page" @endif>{{ __($category->name) }}</a>
            @endforeach
        </nav>

        @if ($books->isEmpty())
            <div class="mt-10 rounded-3xl border-2 border-dashed border-ink/30 p-10 text-center">
                <p class="font-display text-xl font-bold text-plum">{{ __('Tiada buku ditemui') }}</p>
                <p class="mt-1 text-ink/80">{{ __('Cuba kategori atau umur lain.') }}</p>
            </div>
        @else
            <ul class="mt-8 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($books as $book)
                    <li>
                        <a href="{{ route('catalog.show', $book) }}" class="group block rounded-2xl focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-biru">
                            <x-book-cover :book="$book" class="transition group-hover:-translate-y-1" />
                            <p class="mt-3 font-display text-lg leading-tight font-bold group-hover:text-plum">{{ $book->localTitle() }}</p>
                            <p class="text-sm text-ink/70">{{ $book->author }}</p>
                            <p class="mt-1 flex flex-wrap gap-1 text-xs font-bold">
                                <span class="rounded-full bg-lavender px-2 py-0.5">{{ $book->age_group->getLabel() }}</span>
                                @foreach ($book->categories as $category)
                                    <span class="rounded-full bg-biru/10 px-2 py-0.5 text-biru">{{ __($category->name) }}</span>
                                @endforeach
                            </p>
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-10">{{ $books->links() }}</div>
        @endif
    </div>
</x-layouts.app>
