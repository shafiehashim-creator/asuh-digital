<x-layouts.child title="Rak buku" :child="$child">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <h1 class="font-display text-3xl font-extrabold text-magenta sm:text-4xl">Rak buku {{ $child->nickname }}</h1>
        <p class="mt-1 text-lg font-semibold text-plum">Jom Jadi Baik! Pilih buku untuk dibaca.</p>

        <a href="{{ route('child-mode.badges') }}" class="mt-4 flex min-h-11 items-center gap-3 rounded-2xl border-2 border-ink bg-white px-4 py-2 shadow-[0_3px_0_var(--color-ink)] hover:bg-kuning/30">
            <span class="font-display font-bold">Lencana saya</span>
            <span class="flex-1 truncate text-2xl" aria-hidden="true">{{ $badges->map(fn ($row) => $row->badge->emoji())->join(' ') }}</span>
            <span class="text-sm font-bold text-biru">{{ $badges->count() }} / {{ count(App\Enums\Badge::cases()) }}</span>
        </a>

        @if ($recent->isNotEmpty())
            <h2 class="mt-8 font-display text-2xl font-bold text-plum">Sambung membaca</h2>
            <div class="mt-3 grid grid-cols-2 gap-5 sm:grid-cols-4">
                @foreach ($recent as $session)
                    <div>
                        <a href="{{ route('reader.show', $session->book) }}" class="group block">
                            <x-book-cover :book="$session->book" class="transition group-hover:-translate-y-1" />
                            <p class="mt-2 font-display text-lg leading-tight font-bold group-hover:text-plum">{{ $session->book->title }}</p>
                            <p class="text-sm font-semibold text-biru">Halaman {{ $session->last_page }}</p>
                        </a>
                        @if (in_array($session->book_id, $quizBookIds, true))
                            <a href="{{ route('child-mode.quiz.show', $session->book) }}" class="inline-flex min-h-11 items-center text-sm font-bold text-magenta underline">Jawab kuiz</a>
                        @endif
                    </div>
                @endforeach
            </div>
            <h2 class="mt-10 font-display text-2xl font-bold text-plum">Semua buku</h2>
        @endif

        @if ($books->isEmpty())
            <div class="mt-8 rounded-3xl border-2 border-ink bg-white p-6 text-center shadow-[0_4px_0_var(--color-ink)]">
                <p class="font-display text-xl font-bold">Rak ini masih kosong.</p>
                <p class="mt-1 text-ink/80">Minta ibu atau ayah tambah buku untuk kamu.</p>
            </div>
        @else
            <div class="mt-6 grid grid-cols-2 gap-5 sm:grid-cols-4 lg:grid-cols-5">
                @foreach ($books as $book)
                    <a href="{{ route('reader.show', $book) }}" class="group block">
                        <x-book-cover :book="$book" class="transition group-hover:-translate-y-1" />
                        <p class="mt-2 font-display text-lg leading-tight font-bold group-hover:text-plum">{{ $book->title }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.child>
