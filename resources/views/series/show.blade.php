<x-layouts.app :title="$series->localName()">
    <div class="mx-auto max-w-5xl px-4 py-6 sm:py-10">
        <x-catalog-preview-notice />

        <a href="{{ route('catalog.index') }}" class="pautan text-sm">&larr; {{ __('Kembali ke katalog') }}</a>

        <p class="mt-6 text-sm font-extrabold tracking-wide text-biru uppercase">📚 {{ __('Siri · :count buku', ['count' => $books->count()]) }}</p>
        <h1 class="mt-1 font-display text-3xl font-extrabold text-magenta sm:text-4xl">{{ $series->localName() }}</h1>
        @if ($series->localDescription())
            <div class="mt-3 max-w-3xl space-y-3 text-ink/90">
                @foreach (preg_split('/\n\s*\n/', trim($series->localDescription())) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>
        @endif
        <p class="mt-3 text-ink/80">{{ __('Baca ikut turutan, dari buku pertama.') }}</p>

        @unless ($hasPass)
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border-2 border-ink bg-kuning px-5 py-4">
                <p class="font-bold">{{ __('Semua buku dalam siri ini termasuk dalam Langganan Tahunan.') }}</p>
                <a href="{{ route('pass.show') }}" class="btn-gelap">{{ __('Dapatkan Langganan Tahunan') }}</a>
            </div>
        @endunless

        <ol class="mt-8 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($books as $book)
                <li>
                    <a href="{{ route('catalog.show', $book) }}" class="group block rounded-2xl focus-visible:outline-3 focus-visible:outline-offset-4 focus-visible:outline-biru">
                        <div class="relative">
                            <x-book-cover :book="$book" class="transition group-hover:-translate-y-1" />
                            <span class="absolute top-2 left-2 rounded-full border-2 border-ink bg-white px-2 py-0.5 text-xs font-extrabold">{{ $book->seriesLabel() ?? __('Buku :number', ['number' => $loop->iteration]) }}</span>
                        </div>
                        <p class="mt-3 font-display text-lg leading-tight font-bold group-hover:text-plum">{{ $book->localTitle() }}</p>
                        <p class="text-sm text-ink/70">{{ $book->author }}</p>
                        @if ($book->readable)
                            <p class="mt-1 text-xs font-bold text-biru">✓ {{ __('Boleh dibaca') }}</p>
                        @endif
                    </a>
                </li>
            @endforeach
        </ol>
    </div>
</x-layouts.app>
