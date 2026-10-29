@php
    use App\Enums\Game;

    $config = [
        'content' => $content,
        'progressUrl' => $progressUrl,
        'secondsLeft' => $secondsLeft,
        'csrf' => csrf_token(),
        'level' => $level,
        'unlocked' => $unlocked,
        'game' => $game->value,
    ];
    $tile = 'rounded-2xl border-2 border-ink bg-white shadow-[0_3px_0_var(--color-ink)]';
    // A level may be played by another component (Pilih yang Baik plays as a quiz from Sederhana).
    $gameComponent = $content['component'] ?? $game->component();
@endphp

<x-layouts.child :title="$game->label()" :child="$child">
    <div class="mx-auto max-w-3xl px-4 py-6" x-data="{{ $gameComponent }}(@js($config))">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('child-mode.home') }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-ink bg-white px-4 font-bold shadow-[0_2px_0_var(--color-ink)]">← Rak</a>
            <div class="flex items-center gap-2">
                <p x-show="minutesLeft !== null" x-cloak class="rounded-full bg-white px-3 py-1 text-sm font-bold text-biru">Baki masa: <span x-text="minutesLeft"></span> minit</p>
                <button type="button" @click="toggleSound()" class="grid size-11 place-items-center rounded-full border-2 border-ink bg-white text-xl shadow-[0_2px_0_var(--color-ink)]"
                    :aria-label="sound ? 'Matikan bunyi' : 'Hidupkan bunyi'" :aria-pressed="sound.toString()">
                    <span x-text="sound ? '🔊' : '🔇'" aria-hidden="true"></span>
                </button>
            </div>
        </div>

        <h1 class="mt-4 flex items-center gap-3 font-display text-3xl font-extrabold text-plum">
            <span class="grid size-12 place-items-center rounded-full border-2 border-ink bg-kuning text-2xl" aria-hidden="true">{{ $game->emoji() }}</span>
            {{ $game->label() }}
        </h1>
        <p class="mt-1 font-semibold text-ink/75">{{ $game->description() }}@if ($best) · Markah terbaik: {{ $best }}@endif</p>

        {{-- Levels: the next one opens with three stars. --}}
        <nav class="mt-4 flex gap-2" aria-label="Tahap">
            @foreach (App\Enums\Game::LEVELS as $number => $name)
                @if ($number <= $unlocked)
                    <a href="{{ route('child-mode.game', [$game, 'tahap' => $number]) }}" @if ($number === $level) aria-current="page" @endif
                        @class(['inline-flex min-h-11 items-center rounded-full border-2 border-ink px-4 text-sm font-extrabold', 'bg-plum text-white' => $number === $level, 'bg-white' => $number !== $level])>{{ $name }}</a>
                @else
                    <span class="inline-flex min-h-11 items-center rounded-full border-2 border-ink/30 bg-white/60 px-4 text-sm font-bold text-ink/50" title="Dapatkan 3 bintang di tahap {{ App\Enums\Game::LEVELS[$number - 1] }} untuk membukanya">🔒 {{ $name }}</span>
                @endif
            @endforeach
        </nav>

        {{-- Time is up for today --}}
        <div x-show="timeUp" x-cloak class="mt-6 {{ $tile }} p-6 text-center">
            <p class="text-5xl" aria-hidden="true">⏰</p>
            <p class="mt-2 font-display text-2xl font-bold">Masa bermain hari ini sudah habis.</p>
            <p class="mt-1 text-ink/75">Jom baca buku pula! Esok boleh main lagi.</p>
            <a href="{{ route('child-mode.home') }}" class="btn-utama mt-4">Ke rak buku</a>
        </div>

        {{-- Round finished --}}
        <div x-show="done && !timeUp" x-cloak class="mt-6 {{ $tile }} p-6 text-center">
            <p class="text-5xl" aria-hidden="true"><span x-text="'⭐'.repeat(stars)"></span></p>
            <p class="mt-2 font-display text-2xl font-bold">Tahniah! Markah kamu <span x-text="score"></span></p>
            <p x-show="!newLevel" class="mt-1 text-ink/75" x-text="stars < 3 && level === unlocked && level < 3 ? 'Dapatkan 3 bintang untuk membuka tahap seterusnya.' : 'Jom Jadi Baik!'"></p>
            <p x-show="newLevel" x-cloak class="mt-2 rounded-2xl bg-kuning px-4 py-2 font-display text-xl font-extrabold">🎉 Tahap <span x-text="{{ Js::from(App\Enums\Game::LEVELS) }}[unlocked]"></span> dibuka!</p>
            <div class="mt-4 flex flex-wrap justify-center gap-3">
                <a x-show="newLevel" x-cloak :href="{{ Js::from(route('child-mode.game', $game)) }} + '?tahap=' + unlocked" class="btn-utama">Cuba tahap baharu →</a>
                <button type="button" :class="newLevel ? 'btn-gelap' : 'btn-utama'" @click="restart()">Main lagi</button>
                <a href="{{ route('child-mode.home') }}" class="btn-gelap">Ke rak buku</a>
            </div>
        </div>

        <div x-show="!done && !timeUp" class="mt-6">
            <p class="mb-3 rounded-2xl bg-white/70 px-4 py-2 text-sm font-bold text-plum">🧠 {{ $game->levelHint($level) }}</p>
            @switch($gameComponent)
                @case('gameMemory')
                    <p class="font-semibold">Langkah: <span x-text="moves"></span> · Pasangan: <span x-text="matched.length"></span> / {{ $content['count'] }}
                        <span x-show="peeking" x-cloak class="ml-1 text-magenta">· Ingat kedudukan kad!</span></p>
                    <div @class(['mt-3 grid gap-3', 'grid-cols-3 sm:grid-cols-4' => $content['count'] <= 6, 'grid-cols-4' => $content['count'] > 6])>
                        <template x-for="card in cards" :key="card.key">
                            <button type="button" @click="flip(card)"
                                class="grid aspect-square place-items-center rounded-2xl border-2 border-ink p-1 text-center font-display font-bold shadow-[0_3px_0_var(--color-ink)] transition"
                                :class="matched.includes(card.id) ? 'bg-kuning' : (isUp(card) ? 'bg-white' : 'bg-plum text-white')"
                                :aria-label="isUp(card) ? card.face : 'Kad tertutup'">
                                <span x-show="isUp(card)" :class="card.word ? 'text-sm sm:text-base leading-tight' : 'text-4xl'" x-text="card.face"></span>
                                <span x-show="!isUp(card)" class="text-2xl" aria-hidden="true">?</span>
                            </button>
                        </template>
                    </div>
                    @break

                @case('gameGoodChoice')
                    <p class="font-semibold">Soalan <span x-text="index + 1"></span> / <span x-text="items.length"></span></p>
                    <div class="mt-3 {{ $tile }} p-5">
                        <p class="font-display text-xl leading-snug font-bold" x-text="item?.text"></p>
                    </div>
                    <div x-show="!feedback" class="mt-4 grid grid-cols-2 gap-3">
                        <button type="button" class="min-h-20 rounded-2xl border-2 border-ink bg-biru font-display text-xl font-extrabold text-white shadow-[0_3px_0_var(--color-ink)]" @click="answer(true)">👍 Baik</button>
                        <button type="button" class="min-h-20 rounded-2xl border-2 border-ink bg-oren font-display text-xl font-extrabold text-ink shadow-[0_3px_0_var(--color-ink)]" @click="answer(false)">👎 Tak baik</button>
                    </div>
                    <div x-show="feedback" x-cloak class="mt-4 flex items-center gap-4 {{ $tile }} p-4">
                        <img :src="feedback?.ok ? @js(Vite::asset('resources/images/brand/anis.webp')) : @js(Vite::asset('resources/images/brand/syaitan-gemuk.webp'))" alt="" class="h-24 w-auto shrink-0 rounded-xl">
                        <div>
                            <p class="font-display text-xl font-extrabold" :class="feedback?.ok ? 'text-biru' : 'text-magenta'" x-text="feedback?.ok ? 'Betul! 🎉' : 'Alamak, penggoda menang kali ini!'"></p>
                            <p class="mt-1" x-text="feedback?.why"></p>
                            <button type="button" class="btn-utama mt-3" @click="next()">Seterusnya →</button>
                        </div>
                    </div>
                    @break

                @case('gameOrder')
                    <p class="font-semibold">Susun: <span class="font-display text-xl text-magenta" x-text="set?.title"></span>
                        <span x-show="reverse" x-cloak class="block text-sm text-biru">⬅️ Mulakan dari yang TERAKHIR, kembali ke yang pertama.</span>
                        <span x-show="impostor" x-cloak class="block text-sm text-oren">🕵️ Ada satu penyusup yang bukan dari kumpulan ini. Jangan pilih!</span></p>
                    <ol class="mt-3 space-y-2">
                        <template x-for="(order, i) in placed" :key="order">
                            <li class="flex items-center gap-3 rounded-2xl border-2 border-ink bg-kuning px-4 py-2 font-bold">
                                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-white" x-text="i + 1"></span>
                                <span x-text="set.items[order]"></span>
                            </li>
                        </template>
                    </ol>
                    <div x-show="!setDone" class="mt-4 grid gap-2 sm:grid-cols-2">
                        <template x-for="tile in tiles" :key="tile.order">
                            <button type="button" x-show="!placed.includes(tile.order)" @click="tap(tile)"
                                class="min-h-14 rounded-2xl border-2 border-ink bg-white px-4 py-2 text-left font-bold shadow-[0_3px_0_var(--color-ink)]"
                                :class="shaking === tile.order && 'game-shake border-magenta'" x-text="tile.text"></button>
                        </template>
                    </div>
                    <div x-show="setDone" x-cloak class="mt-4 text-center">
                        <p class="font-display text-2xl font-extrabold text-biru">Betul semuanya! 🎉</p>
                        <p x-show="impostor" class="mt-1 font-semibold">Penyusupnya: <span class="text-oren" x-text="impostor"></span></p>
                        <button type="button" class="btn-utama mt-3" @click="nextSet()" x-text="setIndex + 1 < sets.length ? 'Seterusnya →' : 'Lihat markah'"></button>
                    </div>
                    @break

                @case('gameWordSearch')
                    <p class="font-semibold">Tekan huruf pertama, kemudian huruf terakhir perkataan.</p>
                    @if ($content['clues'] ?? null)
                        {{-- Sukar: only clues, the word shows once found. --}}
                        <ol class="mt-3 space-y-1">
                            <template x-for="(word, i) in words" :key="word">
                                <li class="rounded-xl border-2 border-ink/20 px-3 py-1 text-sm font-bold" :class="found.includes(word) ? 'bg-kuning' : 'bg-white'">
                                    <span x-text="(i + 1) + '. ' + {{ Js::from($content['clues']) }}[word]"></span>
                                    <span x-show="found.includes(word)" class="font-extrabold text-biru" x-text="'→ ' + word"></span>
                                    <span x-show="!found.includes(word)" class="text-ink/60" x-text="'(' + word.length + ' huruf)'"></span>
                                </li>
                            </template>
                        </ol>
                    @else
                        <ul class="mt-3 flex flex-wrap gap-2">
                            <template x-for="word in words" :key="word">
                                <li class="rounded-full border-2 border-ink px-3 py-1 text-sm font-extrabold" :class="found.includes(word) ? 'bg-kuning line-through' : 'bg-white'" x-text="word"></li>
                            </template>
                        </ul>
                    @endif
                    <div class="mt-4 grid gap-1" :style="`grid-template-columns: repeat(${size}, minmax(0, 1fr))`">
                        <template x-for="(letter, cell) in grid" :key="cell">
                            <button type="button" @click="tapCell(cell)"
                                class="grid aspect-square place-items-center rounded-lg border-2 font-display text-lg font-extrabold sm:text-xl"
                                :class="start === cell ? 'border-ink bg-biru text-white' : (foundCells.includes(cell) ? 'border-ink bg-kuning' : 'border-ink/20 bg-white')"
                                x-text="letter"></button>
                        </template>
                    </div>
                    @break

                @case('gameSliding')
                    <p class="font-semibold">Langkah: <span x-text="moves"></span> · Tekan kepingan di sebelah ruang kosong.</p>
                    <div class="mt-3 flex flex-wrap items-start gap-4">
                        <div class="grid w-full max-w-sm gap-1 rounded-2xl border-2 border-ink bg-ink p-1" :style="`grid-template-columns: repeat(${size}, minmax(0, 1fr))`">
                            <template x-for="(tile, cell) in tiles" :key="tile">
                                <button type="button" @click="slide(cell)" :disabled="isBlank(tile)"
                                    class="relative aspect-square rounded-lg bg-lavender transition-transform active:scale-95"
                                    :style="isBlank(tile) ? '' : pieceStyle(tile)" :aria-label="isBlank(tile) ? 'Ruang kosong' : 'Kepingan ' + (tile + 1)">
                                    @if ($content['numbers'])
                                        <span x-show="!isBlank(tile)" class="absolute top-1 left-1 grid size-6 place-items-center rounded-full bg-white/90 text-xs font-extrabold" x-text="tile + 1"></span>
                                    @endif
                                </button>
                            </template>
                        </div>
                        <div class="w-28 shrink-0">
                            <p class="text-sm font-bold">Gambar penuh:</p>
                            <img :src="image" alt="" class="mt-1 w-28 rounded-xl border-2 border-ink">
                        </div>
                    </div>
                    @break

                @case('gameSudoku')
                    <p class="font-semibold">Tekan kotak kosong, kemudian pilih gambar. Setiap baris, lajur dan kotak tebal ada semua gambar sekali.
                        <span x-show="checkLater" x-cloak class="block text-sm text-biru">Tiada petunjuk salah di tahap ini. Bila penuh, tekan Semak.</span></p>
                    <div class="mx-auto mt-3 grid w-full max-w-sm overflow-hidden rounded-2xl border-4 border-ink bg-white" :style="`grid-template-columns: repeat(${size}, minmax(0, 1fr))`">
                        <template x-for="(entry, cell) in entries" :key="cell">
                            <button type="button" @click="pick(cell)"
                                class="grid aspect-square place-items-center border border-ink/20 text-3xl"
                                :class="[borderClass(cell), wrongCells.includes(cell) ? 'bg-magenta' : (selected === cell ? 'bg-kuning' : (given[cell] ? 'bg-lavender' : 'bg-white'))]"
                                :aria-label="entry === null ? 'Kotak kosong' : symbols[entry]">
                                <span x-text="entry === null ? '' : symbols[entry]"></span>
                            </button>
                        </template>
                    </div>
                    <div class="mx-auto mt-4 flex max-w-sm flex-wrap justify-center gap-2">
                        <template x-for="(symbol, value) in symbols" :key="value">
                            <button type="button" @click="place(value)"
                                class="grid size-14 place-items-center rounded-2xl border-2 border-ink bg-white text-3xl shadow-[0_3px_0_var(--color-ink)]"
                                :class="shaking === value && 'game-shake border-magenta'" x-text="symbol"></button>
                        </template>
                    </div>
                    <div x-show="checkLater" x-cloak class="mt-4 text-center">
                        <button type="button" class="btn-utama" :disabled="!full" :class="!full && 'opacity-50'" @click="check()">✅ Semak</button>
                    </div>
                    <p class="mt-2 text-center text-sm font-semibold"><span x-text="checkLater ? 'Semakan tidak lulus' : 'Silap'"></span>: <span x-text="mistakes"></span></p>
                    @break

                @default
                    {{-- Hijaiyah, prophets, manners, maths, science, patterns, best action and stories: multiple choice --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 font-semibold">
                        <p x-show="!survival">Soalan <span x-text="index + 1"></span> / <span x-text="questions.length"></span></p>
                        <p x-show="survival" x-cloak>Betul: <span x-text="correct"></span> · Sasaran <span x-text="survival"></span></p>
                        <p x-show="maxLives !== null" x-cloak class="text-lg" :aria-label="'Nyawa: ' + lives" x-text="hearts"></p>
                        <p x-show="countdown !== null" x-cloak class="rounded-full bg-white px-3 py-1 font-display text-lg font-extrabold" :class="countdown <= 3 && 'text-magenta'">⏱️ <span x-text="countdown"></span></p>
                    </div>
                    <div class="mt-3 {{ $tile }} p-5 text-center">
                        <p class="font-display text-xl leading-snug font-bold" x-text="question?.prompt"></p>
                        <p x-show="question?.big" class="mt-3 leading-tight font-display font-extrabold break-words whitespace-pre-wrap"
                            :class="question?.rtl ? 'text-8xl font-normal' : ((question?.big ?? '').length > 18 ? 'text-3xl sm:text-4xl' : 'text-4xl sm:text-5xl')"
                            :dir="question?.rtl ? 'rtl' : 'ltr'" :lang="question?.rtl ? 'ar' : 'ms'" x-text="question?.big"></p>
                    </div>
                    <p x-show="question?.pick" x-cloak class="mt-3 text-center text-sm font-bold text-biru">Tekan dua nombor. Tekan sekali lagi untuk batal.</p>
                    <div class="mt-4 grid gap-3" :class="(question?.choices ?? []).some((c) => c.length > 24) ? 'grid-cols-1' : (question?.choices?.length === 3 && question?.keepOrder ? 'grid-cols-3' : 'sm:grid-cols-2')">
                        <template x-for="choice in question?.choices ?? []" :key="choice">
                            <button type="button" @click="choose(choice)"
                                class="min-h-14 rounded-2xl border-2 border-ink px-4 py-2 font-display font-bold shadow-[0_3px_0_var(--color-ink)]"
                                :class="[
                                    /^\p{Extended_Pictographic}\uFE0F?$/u.test(choice) ? 'text-4xl' : (/[\u0600-\u06FF]/.test(choice) ? 'text-5xl font-normal' : (choice.length > 24 ? 'text-left text-base' : 'text-lg')),
                                    question.pick
                                        ? (selection.includes(choice) ? (picked === null ? 'bg-kuning' : (lastOk ? 'bg-kuning' : 'bg-magenta text-white')) : 'bg-white')
                                        : (picked === null ? 'bg-white' : (choice === question.answer ? 'bg-kuning' : (choice === picked ? 'bg-magenta text-white' : 'bg-white opacity-60'))),
                                ]"
                                :lang="/[\u0600-\u06FF]/.test(choice) ? 'ar' : null" x-text="choice"></button>
                        </template>
                    </div>
                    {{-- Why, after answering: a best action, a story, or the right pair of numbers. --}}
                    <p x-show="picked !== null && (question?.why || (question?.pick && !lastOk))" x-cloak class="mt-4 rounded-2xl border-2 border-ink bg-white p-3 font-semibold"
                        x-text="(lastOk ? '✅ ' : '💡 ') + (question?.pick ? 'Contoh jawapan: ' + question.answer : question?.why)"></p>
            @endswitch
        </div>
    </div>
</x-layouts.child>
