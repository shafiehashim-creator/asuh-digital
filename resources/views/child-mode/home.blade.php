<x-layouts.child title="Rak buku" :child="$child">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <h1 class="font-display text-3xl font-extrabold text-magenta sm:text-4xl">Rak buku {{ $child->nickname }}</h1>
        <p class="mt-1 text-lg font-semibold text-plum">Jom Jadi Baik! Pilih buku untuk dibaca.</p>

        <a href="{{ route('child-mode.badges') }}" class="mt-4 flex min-h-11 items-center gap-3 rounded-2xl border-2 border-ink bg-white px-4 py-2 shadow-[0_3px_0_var(--color-ink)] hover:bg-kuning/30">
            <span class="font-display font-bold">Lencana saya</span>
            <span class="flex-1 truncate text-2xl" aria-hidden="true">{{ $badges->map(fn ($row) => $row->badge->emoji())->join(' ') }}</span>
            <span class="text-sm font-bold text-biru">{{ $badges->count() }} / {{ count(App\Enums\Badge::cases()) }}</span>
        </a>

        @if ($promises->isNotEmpty())
            <section class="mt-4 rounded-2xl border-2 border-ink bg-kuning p-4 shadow-[0_3px_0_var(--color-ink)]" aria-labelledby="promises-heading">
                <h2 id="promises-heading" class="font-display text-lg font-bold">🎁 Janji ibu ayah</h2>
                <ul class="mt-2 space-y-2">
                    @foreach ($promises as $promise)
                        <li class="flex items-center gap-3 rounded-xl bg-white px-3 py-2">
                            <span class="text-2xl" aria-hidden="true">{{ $promise->isAchieved() ? '🎉' : $promise->badge->emoji() }}</span>
                            <span class="min-w-0">
                                @if ($promise->isAchieved())
                                    <span class="block font-bold text-magenta">Tahniah! Kamu dapat lencana {{ $promise->badge->label() }}.</span>
                                    <span class="block text-sm">Minta ganjaran daripada ibu ayah: <strong>{{ $promise->reward }}</strong></span>
                                @else
                                    <span class="block font-bold">Dapatkan lencana {{ $promise->badge->label() }} → {{ $promise->reward }}</span>
                                    <span class="block text-sm text-ink/70">{{ $promise->badge->description() }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (App\Support\BrandVideo::shownOn('child_mode'))
            <a href="{{ route('child-mode.song') }}" class="mt-4 flex min-h-16 items-center gap-4 rounded-2xl border-2 border-ink bg-plum px-4 py-3 text-white shadow-[0_3px_0_var(--color-ink)] hover:bg-plum/90">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-full border-2 border-ink bg-kuning text-2xl text-ink" aria-hidden="true">♪</span>
                <span class="font-display text-lg font-bold">{{ App\Support\BrandVideo::title() }}</span>
            </a>
        @endif

        @if (session('video_locked'))
            <p role="status" class="mt-4 rounded-2xl border-2 border-ink bg-kuning p-3 font-bold">{{ session('video_locked') }}</p>
        @endif

        @if ($videos->isNotEmpty())
            <div class="mt-8 flex flex-wrap items-end justify-between gap-2">
                <h2 class="font-display text-2xl font-bold text-plum">Video animasi</h2>
                @if ($videoSecondsLeft !== null)
                    <p class="text-sm font-bold text-biru">Baki masa menonton hari ini: {{ intdiv($videoSecondsLeft + 59, 60) }} minit</p>
                @endif
            </div>
            <div class="mt-3 flex snap-x gap-4 overflow-x-auto pb-2">
                @foreach ($videos as $item)
                    @php($video = $item['video'])
                    <a href="{{ route('child-mode.video', $video) }}" class="group block w-64 shrink-0 snap-start">
                        <div class="relative aspect-video overflow-hidden rounded-2xl border-2 border-ink bg-plum shadow-[0_3px_0_var(--color-ink)]">
                            @if ($video->thumbnail_path)
                                <img src="{{ Storage::disk('public')->url($video->thumbnail_path) }}" alt="" loading="lazy" @class(['h-full w-full object-cover', 'opacity-40 grayscale' => $item['locked']])>
                            @else
                                <div class="flex h-full items-center justify-center p-3 text-center font-display text-lg font-extrabold text-white">{{ $video->title }}</div>
                            @endif
                            <span class="absolute bottom-2 left-2 flex size-10 items-center justify-center rounded-full border-2 border-ink bg-kuning text-lg text-ink" aria-hidden="true">{{ $item['locked'] ? '🔒' : '▶' }}</span>
                            @if ($video->durationLabel())
                                <span class="absolute right-2 bottom-2 rounded-full bg-ink/80 px-2 text-xs font-bold text-white">{{ $video->durationLabel() }}</span>
                            @endif
                        </div>
                        <p class="mt-2 font-display leading-tight font-bold group-hover:text-plum">{{ $video->title }}</p>
                        @if ($item['locked'])
                            <p class="text-sm font-semibold text-magenta">{{ $item['locked'] }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif

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
