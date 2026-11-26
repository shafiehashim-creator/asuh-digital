<x-layouts.app :title="'Markah: '.$assignment->quizGame->title">
    <div class="mx-auto max-w-4xl space-y-8 px-4 py-10">
        <div>
            <a href="{{ route('teacher.quiz-games.index') }}" class="pautan text-sm">← Kuiz Interaktif</a>
            <h1 class="mt-2 font-display text-3xl font-extrabold text-magenta">{{ $assignment->quizGame->title }}</h1>
            <p class="text-ink/80">{{ $assignment->classroom->name }}{{ $assignment->due_at ? ' · hingga '.$assignment->due_at->translatedFormat('j M Y, g:i A') : '' }}</p>
        </div>

        @if (session('status') === 'quiz-assigned')
            <div role="status" class="rounded-3xl border-2 border-ink bg-kuning p-5 shadow-[0_4px_0_var(--color-ink)]">
                <p class="font-display text-xl font-bold">Kuiz sudah diberi kepada {{ $assignment->classroom->name }}.</p>
                <p class="mt-1">Minta murid buka <strong>{{ str_replace(['https://', 'http://'], '', route('pupil.join')) }}</strong>, masukkan kod kelas <strong class="font-mono">{{ $assignment->classroom->code }}</strong>, tekan nama mereka, kemudian pilih kuiz ini di bahagian <strong>Kuiz daripada cikgu</strong>.</p>
            </div>
        @endif

        <section aria-labelledby="board-heading" class="rounded-3xl border-2 border-ink bg-white p-5 shadow-[0_4px_0_var(--color-ink)]"
            x-data="{ live: true }" x-init="setInterval(() => { if (live && document.visibilityState === 'visible') location.reload() }, 15000)">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="board-heading" class="font-display text-xl font-bold text-plum">🏆 Papan markah</h2>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" x-model="live" class="size-5 accent-plum"> Kemas kini setiap 15 saat</label>
                    <a href="{{ route('teacher.quiz-games.export', $assignment) }}" class="pautan text-sm">Muat turun CSV</a>
                </div>
            </div>
            @if ($results['ranked']->isEmpty())
                <p class="mt-4 text-ink/70">Belum ada murid yang selesai. Papan ini dikemas kini sendiri.</p>
            @else
                <ol class="mt-4 space-y-2">
                    @foreach ($results['ranked'] as $i => $row)
                        <li @class(['flex items-center gap-3 rounded-2xl border-2 p-3', 'border-ink bg-kuning' => $i === 0, 'border-ink/20' => $i > 0])>
                            <span class="w-10 text-center font-display text-2xl font-extrabold">{{ ['🥇', '🥈', '🥉'][$i] ?? $i + 1 }}</span>
                            <span class="min-w-0 flex-1 truncate font-bold">{{ $row['child']->nickname }}</span>
                            <span class="text-sm font-bold text-ink/70">{{ $row['play']->correct }}/{{ $row['play']->total }} betul</span>
                            <span class="w-24 text-right font-extrabold tabular-nums">{{ number_format($row['play']->score) }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
            @if ($results['waiting']->isNotEmpty())
                <p class="mt-4 text-sm"><strong>Belum main ({{ $results['waiting']->count() }}):</strong> {{ $results['waiting']->pluck('nickname')->join(', ') }}</p>
            @endif
        </section>

        <section aria-labelledby="questions-heading" class="rounded-3xl border-2 border-ink bg-white p-5 shadow-[0_4px_0_var(--color-ink)]">
            <h2 id="questions-heading" class="font-display text-xl font-bold text-plum">Soalan mana yang sukar?</h2>
            <ol class="mt-4 space-y-3">
                @foreach ($questions as $i => $stat)
                    @php($percent = $stat['answered'] ? (int) round($stat['right'] / $stat['answered'] * 100) : null)
                    <li>
                        <p class="font-bold">{{ $i + 1 }}. {{ $stat['question']->prompt }}</p>
                        <div class="mt-1 flex items-center gap-3">
                            <div class="h-3 flex-1 overflow-hidden rounded-full bg-lavender">
                                <div @class(['h-full', 'bg-biru' => ($percent ?? 0) >= 60, 'bg-magenta' => ($percent ?? 0) < 60]) style="width: {{ $percent ?? 0 }}%"></div>
                            </div>
                            <span class="w-28 text-right text-sm font-bold tabular-nums">{{ $percent === null ? 'Belum dijawab' : $percent.'% betul' }}</span>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        <form method="POST" action="{{ route('teacher.quiz-games.destroy', $assignment) }}" onsubmit="return confirm('Tarik balik kuiz ini daripada kelas? Markah murid juga akan hilang dari senarai ini.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="pautan text-sm text-magenta">Tarik balik kuiz daripada kelas</button>
        </form>
    </div>
</x-layouts.app>
