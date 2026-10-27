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

        @if (session('game_locked'))
            <p role="status" class="mt-4 rounded-2xl border-2 border-ink bg-kuning p-3 font-bold">{{ session('game_locked') }}</p>
        @endif

        @if (session('video_locked'))
            <p role="status" class="mt-4 rounded-2xl border-2 border-ink bg-kuning p-3 font-bold">{{ session('video_locked') }}</p>
        @endif

        @if ($videos->isNotEmpty() && $rows === null)
            <div class="mt-8 flex flex-wrap items-end justify-between gap-2">
                <h2 class="font-display text-2xl font-bold text-plum">Video animasi</h2>
                @if ($videoSecondsLeft !== null)
                    <p class="text-sm font-bold text-biru">Baki masa menonton hari ini: {{ intdiv($videoSecondsLeft + 59, 60) }} minit</p>
                @endif
            </div>
            <div class="mt-3 flex snap-x gap-4 overflow-x-auto pb-2">
                @foreach ($videos as $item)
                    @include('child-mode.partials.video-card', ['video' => $item['video'], 'locked' => $item['locked']])
                @endforeach
            </div>
        @endif

        @if (! empty($games))
            {{-- Games for subscribed families, under the videos. --}}
            <section class="mt-8" aria-labelledby="games-heading">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <h2 id="games-heading" class="flex items-center gap-2 font-display text-2xl font-bold text-plum">
                        <span class="flex size-11 items-center justify-center rounded-full border-2 border-ink bg-kuning text-2xl" aria-hidden="true">🎮</span>
                        Permainan
                    </h2>
                    @if ($gameSecondsLeft !== null)
                        <p class="text-sm font-bold text-biru">Baki masa bermain hari ini: {{ intdiv($gameSecondsLeft + 59, 60) }} minit</p>
                    @endif
                </div>
                <div class="-mx-4 mt-3 flex snap-x scroll-px-4 items-stretch gap-4 overflow-x-auto px-4 pb-3">
                    @foreach ($games as $game)
                        <a href="{{ route('child-mode.game', $game) }}" class="flex w-40 shrink-0 snap-start flex-col rounded-2xl border-2 border-ink bg-white p-4 shadow-[0_3px_0_var(--color-ink)] transition hover:-translate-y-1 hover:bg-kuning/30 sm:w-44">
                            <span class="flex size-14 items-center justify-center rounded-full border-2 border-ink bg-lavender text-3xl" aria-hidden="true">{{ $game->emoji() }}</span>
                            <span class="mt-3 font-display text-lg leading-tight font-bold">{{ $game->label() }}</span>
                            @if (($gameLevels[$game->value] ?? 1) > 1)
                                <span class="mt-1 text-xs font-extrabold text-magenta">Tahap {{ App\Enums\Game::LEVELS[$gameLevels[$game->value]] }}</span>
                            @endif
                            @if (($gameBest[$game->value] ?? 0) > 0)
                                <span class="mt-auto pt-2 text-sm font-bold text-biru">⭐ Terbaik: {{ $gameBest[$game->value] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
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
            @if ($rows === null)
                <h2 class="mt-10 font-display text-2xl font-bold text-plum">Semua buku</h2>
            @endif
        @endif

        @if ($rows !== null)
            {{-- A big shelf: one row per category, books and their animations together. --}}
            @if ($videos->isNotEmpty() && $videoSecondsLeft !== null)
                <p class="mt-8 inline-block rounded-full border-2 border-ink bg-white px-4 py-1 text-sm font-bold text-biru">▶ Baki masa menonton hari ini: {{ intdiv($videoSecondsLeft + 59, 60) }} minit</p>
            @endif
            @foreach ($rows as $row)
                <section class="mt-8" aria-label="{{ $row['title'] }}">
                    <h2 class="flex items-center gap-2 font-display text-2xl font-bold text-plum">
                        <span class="flex size-11 items-center justify-center rounded-full border-2 border-ink bg-kuning text-2xl" aria-hidden="true">{{ $row['emoji'] }}</span>
                        {{ $row['title'] }}
                    </h2>
                    <div class="-mx-4 mt-3 flex snap-x scroll-px-4 items-start gap-4 overflow-x-auto px-4 pb-3">
                        @foreach ($row['items'] as $item)
                            @if ($item['type'] === 'video')
                                @include('child-mode.partials.video-card', ['video' => $item['video'], 'locked' => $item['locked']])
                            @else
                                <a href="{{ route('reader.show', $item['book']) }}" class="group block w-36 shrink-0 snap-start sm:w-44">
                                    <x-book-cover :book="$item['book']" class="transition group-hover:-translate-y-1" />
                                    <p class="mt-2 font-display text-lg leading-tight font-bold group-hover:text-plum">{{ $item['book']->title }}</p>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endforeach
        @elseif ($books->isEmpty())
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
