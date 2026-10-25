@php($newBadges = array_map(fn ($code) => App\Enums\Badge::from($code), $result['badges']))

<x-layouts.child title="Keputusan kuiz" :child="$child">
    <div class="mx-auto max-w-xl px-4 py-10 text-center">
        <div class="rounded-3xl border-2 border-ink bg-white p-8 shadow-[0_4px_0_var(--color-ink)]">
            <p class="text-6xl" aria-hidden="true">{{ $result['passed'] ? '🎉' : '💪' }}</p>
            <h1 class="mt-3 font-display text-3xl font-extrabold text-magenta">
                {{ $result['passed'] ? 'Tahniah, '.$child->nickname.'!' : 'Hampir berjaya!' }}
            </h1>
            <p class="mt-2 text-xl font-bold">{{ $result['score'] }} daripada {{ $result['total'] }} betul</p>
            <p class="mt-1 text-ink/80">
                {{ $result['passed'] ? 'Kamu lulus kuiz '.$book->title.'.' : 'Baca semula buku ini dan cuba lagi. Kamu boleh!' }}
            </p>

            @if ($newBadges)
                <div class="mt-6 rounded-2xl bg-lavender p-4">
                    <p class="font-display text-lg font-bold text-plum">Lencana baharu!</p>
                    <ul class="mt-2 flex flex-wrap justify-center gap-4">
                        @foreach ($newBadges as $badge)
                            <li class="flex flex-col items-center">
                                <span class="text-5xl" aria-hidden="true">{{ $badge->emoji() }}</span>
                                <span class="mt-1 font-bold">{{ $badge->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-6 flex flex-wrap justify-center gap-3">
                @unless ($result['passed'])
                    <a href="{{ route('reader.show', $book) }}" class="btn-gelap">Baca semula</a>
                    <a href="{{ route('child-mode.quiz.show', $book) }}" class="btn-utama">Cuba lagi</a>
                @else
                    <a href="{{ route('child-mode.badges') }}" class="btn-gelap">Lencana saya</a>
                    <a href="{{ route('child-mode.home') }}" class="btn-utama">Pilih buku lain</a>
                @endunless
            </div>
        </div>
    </div>
</x-layouts.child>
