<x-layouts.app title="Kuiz Interaktif">
    <div class="mx-auto max-w-5xl space-y-8 px-4 py-10">
        <div>
            <a href="{{ route('teacher.home') }}" class="pautan text-sm">← Portal guru</a>
            <h1 class="mt-2 font-display text-3xl font-extrabold text-magenta">🧠 Kuiz Interaktif</h1>
            <p class="mt-1 max-w-prose text-ink/80">Kuiz bermasa yang disediakan dan disemak oleh ASUH. Beri kuiz kepada kelas anda; murid memainkannya selepas memasukkan kod kelas, dan markah mereka dipaparkan di sini.</p>
        </div>

        @if (session('status') === 'quiz-unassigned')
            <p role="status" class="font-semibold text-biru">Kuiz ditarik balik daripada kelas.</p>
        @endif

        @if ($assignments->isNotEmpty())
            <section aria-labelledby="given-heading">
                <h2 id="given-heading" class="font-display text-xl font-bold text-plum">Sudah diberi kepada kelas</h2>
                <ul class="mt-3 divide-y-2 divide-ink/10 rounded-3xl border-2 border-ink bg-white shadow-[0_4px_0_var(--color-ink)]">
                    @foreach ($assignments as $assignment)
                        <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                            <div class="min-w-0">
                                <p class="font-bold">{{ $assignment->quizGame->title }}</p>
                                <p class="text-sm text-ink/70">{{ $assignment->classroom->name }} · {{ $assignment->plays_count }} cubaan selesai{{ $assignment->due_at ? ' · hingga '.$assignment->due_at->translatedFormat('j M, g:i A') : '' }}</p>
                            </div>
                            <a href="{{ route('teacher.quiz-games.results', $assignment) }}" class="btn-gelap">Lihat markah</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section aria-labelledby="library-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 id="library-heading" class="font-display text-xl font-bold text-plum">Perpustakaan kuiz ASUH</h2>
                <nav class="flex gap-2" aria-label="Umur">
                    <a href="{{ route('teacher.quiz-games.index') }}" @class(['rounded-full border-2 px-3 py-1 text-sm font-bold', 'border-plum bg-plum text-white' => ! $activeAge, 'border-ink/20 bg-white' => $activeAge])>Semua</a>
                    @foreach ([App\Enums\AgeGroup::SixToNine, App\Enums\AgeGroup::TenToThirteen] as $age)
                        <a href="{{ route('teacher.quiz-games.index', ['umur' => $age->value]) }}" @class(['rounded-full border-2 px-3 py-1 text-sm font-bold', 'border-plum bg-plum text-white' => $activeAge === $age, 'border-ink/20 bg-white' => $activeAge !== $age])>{{ $age->getLabel() }}</a>
                    @endforeach
                </nav>
            </div>

            @if ($quizzes->isEmpty())
                <p class="mt-4 rounded-2xl border-2 border-dashed border-ink/30 p-6 text-center font-bold text-ink/70">Belum ada kuiz untuk umur ini.</p>
            @elseif ($classrooms->isEmpty())
                <p class="mt-4 rounded-2xl bg-kuning p-4 font-bold">Cipta kelas dahulu di portal guru sebelum memberi kuiz.</p>
            @endif

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($quizzes as $quiz)
                    <article class="flex flex-col rounded-3xl border-2 border-ink bg-white p-5 shadow-[0_4px_0_var(--color-ink)]">
                        <h3 class="font-display text-xl font-bold">{{ $quiz->title }}</h3>
                        <p class="mt-1 text-sm font-bold text-ink/70">{{ $quiz->questions_count }} soalan · {{ $quiz->seconds_per_question }} saat setiap soalan · {{ $quiz->age_group->getLabel() }}</p>
                        @if ($quiz->description)
                            <p class="mt-2 text-ink/80">{{ $quiz->description }}</p>
                        @endif
                        <div class="mt-auto pt-4">
                            <a href="{{ route('child-mode.quiz-games.show', $quiz) }}" target="_blank" rel="noopener" class="pautan text-sm">▶ Cuba sendiri</a>
                            @if ($classrooms->isNotEmpty())
                                <form method="POST" action="{{ route('teacher.quiz-games.assign', $quiz) }}" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                                    @csrf
                                    <label class="sr-only" for="classroom-{{ $quiz->id }}">Kelas</label>
                                    <select id="classroom-{{ $quiz->id }}" name="classroom" class="input" required>
                                        @foreach ($classrooms as $classroom)
                                            <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn-utama">Beri kepada kelas</button>
                                    <label class="text-sm font-bold sm:col-span-2" for="due-{{ $quiz->id }}">Tarikh akhir (pilihan)
                                        <input id="due-{{ $quiz->id }}" type="datetime-local" name="due_at" class="input mt-1">
                                    </label>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.app>
