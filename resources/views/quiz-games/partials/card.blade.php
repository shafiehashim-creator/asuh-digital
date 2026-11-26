@php($assignment = $quiz->relationLoaded('assignment') ? $quiz->getRelation('assignment') : null)
<a href="{{ route('child-mode.quiz-games.show', $quiz) }}" class="group flex flex-col overflow-hidden rounded-3xl border-2 border-ink bg-white shadow-[0_4px_0_var(--color-ink)] transition hover:-translate-y-1">
    <div class="relative aspect-video bg-gradient-to-br from-plum to-magenta">
        @if ($quiz->cover_path)
            <img src="{{ Storage::disk('public')->url($quiz->cover_path) }}" alt="" loading="lazy" class="h-full w-full object-cover">
        @else
            <span class="absolute inset-0 grid place-items-center text-6xl" aria-hidden="true">🧠</span>
        @endif
        @if ($assignment)
            <span class="absolute top-2 left-2 rounded-full border-2 border-ink bg-kuning px-2 py-0.5 text-xs font-extrabold">📌 Tugasan cikgu{{ $assignment->due_at ? ' · hingga '.$assignment->due_at->translatedFormat('j M') : '' }}</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col p-4">
        <p class="font-display text-xl leading-tight font-bold group-hover:text-plum">{{ $quiz->title }}</p>
        <p class="mt-1 text-sm font-bold text-ink/70">{{ $quiz->questions_count }} soalan · {{ $quiz->age_group->getLabel() }}</p>
        @if ($best)
            <p class="mt-auto pt-2 text-sm font-extrabold text-biru">⭐ Terbaik: {{ number_format($best) }} mata</p>
        @endif
    </div>
</a>
