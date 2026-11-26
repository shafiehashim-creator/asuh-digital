{{-- Kuiz Interaktif player (resources/js/quiz-game.js). Answers are checked on the server. --}}
<x-dynamic-component :component="$child ? 'layouts.child' : 'layouts.app'" :title="$quiz->title" :child="$child">
    <div class="mx-auto max-w-3xl px-4 py-6" x-data="quizGame(@js($config))">
        <div class="flex items-center justify-between gap-3">
            <a href="{{ $config['backUrl'] }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-ink bg-white px-4 font-bold shadow-[0_2px_0_var(--color-ink)]">← Kembali</a>
            <div class="flex items-center gap-2" x-show="stage === 'question' || stage === 'feedback'" x-cloak>
                <span class="rounded-full border-2 border-ink bg-white px-3 py-1 font-extrabold" aria-label="Soalan"><span x-text="index + 1"></span>/<span x-text="questions.length"></span></span>
                <span class="relative rounded-full border-2 border-ink bg-kuning px-3 py-1 font-extrabold tabular-nums" aria-live="polite">
                    <span x-text="shown.toLocaleString()"></span> mata
                    {{-- "+points" floating up from the score after a right answer. --}}
                    <template x-for="k in (stage === 'feedback' && result?.correct ? [floatKey] : [])" :key="k">
                        <span class="qz-float pointer-events-none absolute -top-2 right-0 font-display text-xl font-extrabold text-biru" x-text="'+' + result.points"></span>
                    </template>
                </span>
                <span x-show="streak >= 2" x-transition.scale class="rounded-full border-2 border-ink bg-oren px-3 py-1 font-extrabold" :class="streak >= 3 && 'qz-pulse'">🔥 <span x-text="streak"></span></span>
            </div>
            <button type="button" @click="toggleSound()" class="grid size-11 place-items-center rounded-full border-2 border-ink bg-white text-xl shadow-[0_2px_0_var(--color-ink)]"
                :aria-label="sound ? 'Matikan bunyi' : 'Hidupkan bunyi'"><span x-text="sound ? '🔊' : '🔇'" aria-hidden="true"></span></button>
        </div>

        {{-- Start --}}
        <section x-show="stage === 'intro'" class="mt-6 overflow-hidden rounded-3xl border-2 border-ink bg-white text-center shadow-[0_4px_0_var(--color-ink)]">
            <div class="aspect-video bg-gradient-to-br from-plum to-magenta">
                @if ($quiz->cover_path)
                    <img src="{{ Storage::disk('public')->url($quiz->cover_path) }}" alt="" class="h-full w-full object-cover">
                @else
                    <div class="grid h-full place-items-center text-7xl" aria-hidden="true">🧠</div>
                @endif
            </div>
            <div class="p-6">
                <h1 class="font-display text-3xl font-extrabold text-magenta">{{ $quiz->title }}</h1>
                @if ($quiz->description)
                    <p class="mx-auto mt-2 max-w-prose text-ink/80">{{ $quiz->description }}</p>
                @endif
                <p class="mt-3 font-bold text-ink/70">{{ $quiz->questions_count }} soalan · {{ $quiz->seconds_per_question }} saat setiap soalan</p>
                @if ($assignment)
                    <p class="mt-2 inline-block rounded-full bg-kuning px-3 py-1 text-sm font-extrabold">📌 Tugasan cikgu{{ $assignment->due_at ? ' · hingga '.$assignment->due_at->translatedFormat('j M, g:i A') : '' }}</p>
                @endif
                @if ($best)
                    <p class="mt-2 font-extrabold text-biru">⭐ Markah terbaik kamu: {{ number_format($best) }}</p>
                @endif
                <button type="button" @click="start()" :disabled="busy" class="btn-utama mt-5 min-h-14 px-10 text-xl">▶ Mula</button>
                <p x-show="error" x-text="error" class="mt-3 font-bold text-magenta" role="alert"></p>
            </div>
        </section>

        {{-- Question --}}
        {{-- Keyed by question, so each new question animates in. --}}
        <template x-for="q in (current && (stage === 'question' || stage === 'feedback') ? [index] : [])" :key="q">
            <section class="mt-5">
                <div class="flex items-center gap-3">
                    <div class="h-3 flex-1 overflow-hidden rounded-full border-2 border-ink bg-white" aria-hidden="true">
                        <div class="h-full bg-biru transition-[width] duration-100 ease-linear" :class="timeLeftRatio < 0.25 && 'bg-magenta'" :style="`width: ${timeLeftRatio * 100}%`"></div>
                    </div>
                    <span x-show="stage === 'question'" class="grid size-11 shrink-0 place-items-center rounded-full border-2 border-ink bg-white font-display text-lg font-extrabold tabular-nums"
                        :class="secondsLeft <= 3 && 'qz-pulse bg-magenta text-white'" x-text="secondsLeft" aria-label="Saat tinggal"></span>
                </div>
                <div class="qz-drop mt-4 rounded-3xl border-2 border-ink bg-white p-5 text-center shadow-[0_4px_0_var(--color-ink)]">
                    <img x-show="current.image" :src="current.image" alt="" class="mx-auto mb-4 max-h-56 rounded-2xl object-contain">
                    <h2 class="font-display text-2xl leading-snug font-bold sm:text-3xl" x-text="current.prompt"></h2>
                    <p class="mt-1 text-sm font-bold text-ink/60" x-text="hint"></p>
                </div>

                {{-- Choices: big coloured tiles --}}
                <div x-show="current.type === 'pilihan'" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <template x-for="(option, i) in current.options" :key="i">
                        <button type="button" @click="submit(i)" :disabled="stage !== 'question'" :style="`animation-delay: ${150 + i * 80}ms`"
                            class="qz-pop flex min-h-20 items-center gap-3 rounded-2xl border-2 border-ink px-4 py-3 text-left text-lg font-extrabold text-white shadow-[0_4px_0_var(--color-ink)] transition active:translate-y-1 active:shadow-none disabled:cursor-default"
                            :class="[tileColour(i), feedbackClass(i)]">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-white/25" x-text="'ABCD'[i]"></span>
                            <span x-text="option"></span>
                        </button>
                    </template>
                </div>

                {{-- True or false --}}
                <div x-show="current.type === 'betul_salah'" class="mt-4 grid grid-cols-2 gap-3">
                    <button type="button" @click="submit(true)" :disabled="stage !== 'question'" style="animation-delay: 150ms" class="qz-pop min-h-24 rounded-2xl border-2 border-ink bg-biru text-2xl font-extrabold text-white shadow-[0_4px_0_var(--color-ink)] active:translate-y-1" :class="feedbackClass(true)">✓ Betul</button>
                    <button type="button" @click="submit(false)" :disabled="stage !== 'question'" style="animation-delay: 230ms" class="qz-pop min-h-24 rounded-2xl border-2 border-ink bg-magenta text-2xl font-extrabold text-white shadow-[0_4px_0_var(--color-ink)] active:translate-y-1" :class="feedbackClass(false)">✗ Salah</button>
                </div>

                {{-- Put in order: tap ▲ ▼ to move --}}
                <div x-show="current.type === 'susun'" class="mt-4">
                    <ol class="grid gap-2">
                        <template x-for="(item, i) in order" :key="item">
                            <li class="flex items-center gap-3 rounded-2xl border-2 border-ink bg-white p-3 font-bold shadow-[0_2px_0_var(--color-ink)]">
                                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-lavender font-extrabold" x-text="i + 1"></span>
                                <span class="flex-1" x-text="item"></span>
                                <button type="button" @click="move(i, -1)" :disabled="i === 0 || stage !== 'question'" class="grid size-11 place-items-center rounded-full border-2 border-ink bg-kuning disabled:opacity-30" aria-label="Naik">▲</button>
                                <button type="button" @click="move(i, 1)" :disabled="i === order.length - 1 || stage !== 'question'" class="grid size-11 place-items-center rounded-full border-2 border-ink bg-kuning disabled:opacity-30" aria-label="Turun">▼</button>
                            </li>
                        </template>
                    </ol>
                    <button type="button" x-show="stage === 'question'" @click="submit(order)" class="btn-gelap mt-3 w-full min-h-12 text-lg">Hantar susunan</button>
                </div>

                {{-- Fill in --}}
                <form x-show="current.type === 'isi'" @submit.prevent="submit(typed)" class="mt-4 flex gap-2">
                    <label for="quiz-answer" class="sr-only">Jawapan</label>
                    <input id="quiz-answer" x-model="typed" x-ref="typed" :disabled="stage !== 'question'" autocomplete="off" class="input min-h-14 flex-1 text-xl font-bold" placeholder="Taip jawapan…">
                    <button type="submit" x-show="stage === 'question'" class="btn-gelap min-h-14 px-6 text-lg">Hantar</button>
                </form>

                {{-- After answering --}}
                <div x-show="stage === 'feedback'" x-cloak class="qz-pop mt-4 flex items-center gap-4 rounded-3xl border-2 border-ink p-5 text-left shadow-[0_4px_0_var(--color-ink)]" :class="result?.correct ? 'bg-[#E3F6E8]' : 'bg-[#FDE7EF]'" role="status">
                    {{-- Anis jumps for a right answer; Anas wobbles and thinks for a wrong one. --}}
                    <img :src="result?.correct ? @js(Vite::asset('resources/images/brand/anis.webp')) : @js(Vite::asset('resources/images/brand/anas.webp'))" alt=""
                        class="h-28 w-auto shrink-0 rounded-2xl" :class="result?.correct ? 'qz-jump' : 'qz-wobble'">
                    <div class="min-w-0 flex-1">
                    <p class="font-display text-3xl font-extrabold" x-text="result?.correct ? (streak >= 3 ? '🔥 Hebat!' : '🎉 Betul!') : (timedOut ? '⏰ Masa tamat!' : '😅 Belum tepat')"></p>
                    <p x-show="result?.correct" class="mt-1 text-xl font-extrabold text-biru">+<span x-text="result?.points"></span> mata<span x-show="result?.streak >= 2"> · 🔥 <span x-text="result?.streak"></span> berturut-turut</span></p>
                    <p x-show="!result?.correct" class="mt-1 font-bold">Jawapan: <span x-text="Array.isArray(result?.answer) ? result.answer.join(' → ') : result?.answer"></span></p>
                    <p x-show="result?.explanation" class="mt-2 text-ink/80" x-text="result?.explanation"></p>
                    <button type="button" @click="next()" class="btn-utama mt-4 min-h-12 px-8 text-lg" x-text="index + 1 < questions.length ? 'Seterusnya →' : 'Lihat markah'"></button>
                    </div>
                </div>
            </section>
        </template>

        {{-- End --}}
        <section x-show="stage === 'end'" x-cloak class="qz-drop mt-6 rounded-3xl border-2 border-ink bg-white p-6 text-center shadow-[0_4px_0_var(--color-ink)]">
            <img src="{{ Vite::asset('resources/images/brand/anis.webp') }}" alt="" class="mx-auto h-36 w-auto rounded-2xl" :class="stage === 'end' && stars >= 2 && 'qz-jump'">
            <p class="mt-2 flex justify-center gap-2 text-4xl" :aria-label="stars + ' bintang'">
                <template x-for="n in 3" :key="n">
                    <span :class="n <= starsShown ? 'qz-pop' : 'opacity-20 grayscale'">⭐</span>
                </template>
            </p>
            <h2 class="mt-2 font-display text-4xl font-extrabold text-magenta tabular-nums"><span x-text="shown.toLocaleString()"></span> mata</h2>
            <p class="mt-1 text-lg font-bold"><span x-text="summary?.correct ?? 0"></span> daripada <span x-text="summary?.total ?? questions.length"></span> betul</p>
            <p x-show="summary?.rank" class="mt-2 inline-block rounded-full bg-kuning px-4 py-1 font-extrabold">Kedudukan dalam kelas: #<span x-text="summary?.rank?.place"></span> daripada <span x-text="summary?.rank?.of"></span></p>
            <p x-show="summary?.best" class="mt-2 font-bold text-biru">⭐ Terbaik: <span x-text="summary?.best?.toLocaleString()"></span> mata</p>
            <div class="mt-5 flex flex-wrap justify-center gap-3">
                <button type="button" @click="restart()" class="btn-utama min-h-12 px-6">↺ Main lagi</button>
                <a href="{{ $config['backUrl'] }}" class="inline-flex min-h-12 items-center rounded-full border-2 border-ink bg-white px-6 font-bold">Kembali</a>
            </div>
        </section>
    </div>
</x-dynamic-component>
