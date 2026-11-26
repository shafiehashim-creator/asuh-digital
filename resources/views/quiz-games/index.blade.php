{{-- Kuiz Interaktif: the list for a child (Mod Anak), a pupil (their class's) or a parent trying them. --}}
<x-dynamic-component :component="$child ? 'layouts.child' : 'layouts.app'" title="Kuiz Interaktif" :child="$child">
    <div class="mx-auto max-w-5xl px-4 py-6">
        @if ($child)
            <a href="{{ route('child-mode.home') }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-ink bg-white px-4 font-bold shadow-[0_2px_0_var(--color-ink)]">← Rak</a>
        @endif
        <h1 class="mt-4 flex items-center gap-2 font-display text-3xl font-extrabold text-magenta">🧠 Kuiz Interaktif</h1>
        <p class="mt-1 text-ink/80">
            @if ($child?->isPupil())
                Kuiz yang diberi oleh cikgu kamu.
            @elseif ($child)
                Jawab cepat dan betul untuk dapat mata lebih!
            @else
                Cuba kuiz yang dimainkan anak dalam Mod Anak.
            @endif
        </p>

        @if ($quizzes->isEmpty())
            <div class="mt-8 rounded-3xl border-2 border-dashed border-ink/30 bg-white p-8 text-center">
                <p class="font-display text-xl font-bold text-plum">{{ $child?->isPupil() ? 'Belum ada kuiz daripada cikgu.' : 'Belum ada kuiz lagi.' }}</p>
            </div>
        @else
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($quizzes as $quiz)
                    @include('quiz-games.partials.card', ['quiz' => $quiz, 'best' => $best[$quiz->id] ?? null])
                @endforeach
            </div>
        @endif
    </div>
</x-dynamic-component>
