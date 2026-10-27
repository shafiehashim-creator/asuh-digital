@php
    use App\Enums\Game;

    $config = [
        'content' => $content,
        'progressUrl' => $progressUrl,
        'secondsLeft' => $secondsLeft,
        'csrf' => csrf_token(),
    ];
    $tile = 'rounded-2xl border-2 border-ink bg-white shadow-[0_3px_0_var(--color-ink)]';
@endphp

<x-layouts.child :title="$game->label()" :child="$child">
    <div class="mx-auto max-w-3xl px-4 py-6" x-data="{{ $game->component() }}(@js($config))">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('child-mode.home') }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-ink bg-white px-4 font-bold shadow-[0_2px_0_var(--color-ink)]">← Rak</a>
            <p x-show="minutesLeft !== null" x-cloak class="rounded-full bg-white px-3 py-1 text-sm font-bold text-biru">Baki masa bermain: <span x-text="minutesLeft"></span> minit</p>
        </div>

        <h1 class="mt-4 flex items-center gap-3 font-display text-3xl font-extrabold text-plum">
            <span class="grid size-12 place-items-center rounded-full border-2 border-ink bg-kuning text-2xl" aria-hidden="true">{{ $game->emoji() }}</span>
            {{ $game->label() }}
        </h1>
        <p class="mt-1 font-semibold text-ink/75">{{ $game->description() }}@if ($best) · Markah terbaik: {{ $best }}@endif</p>

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
            <p class="mt-1 text-ink/75">Jom Jadi Baik!</p>
            <div class="mt-4 flex flex-wrap justify-center gap-3">
                <button type="button" class="btn-utama" @click="restart()">Main lagi</button>
                <a href="{{ route('child-mode.home') }}" class="btn-gelap">Ke rak buku</a>
            </div>
        </div>

        <div x-show="!done && !timeUp" class="mt-6">
            @switch($game)
                @case(Game::Memory)
                    <p class="font-semibold">Langkah: <span x-text="moves"></span> · Pasangan: <span x-text="matched.length"></span> / {{ $content['count'] }}</p>
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

                @case(Game::GoodChoice)
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

                @case(Game::Order)
                    <p class="font-semibold">Susun: <span class="font-display text-xl text-magenta" x-text="set?.title"></span></p>
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
                        <button type="button" class="btn-utama mt-3" @click="nextSet()" x-text="setIndex + 1 < sets.length ? 'Seterusnya →' : 'Lihat markah'"></button>
                    </div>
                    @break

                @case(Game::WordSearch)
                    <p class="font-semibold">Tekan huruf pertama, kemudian huruf terakhir perkataan.</p>
                    <ul class="mt-3 flex flex-wrap gap-2">
                        <template x-for="word in words" :key="word">
                            <li class="rounded-full border-2 border-ink px-3 py-1 text-sm font-extrabold" :class="found.includes(word) ? 'bg-kuning line-through' : 'bg-white'" x-text="word"></li>
                        </template>
                    </ul>
                    <div class="mt-4 grid gap-1" :style="`grid-template-columns: repeat(${size}, minmax(0, 1fr))`">
                        <template x-for="(letter, cell) in grid" :key="cell">
                            <button type="button" @click="tapCell(cell)"
                                class="grid aspect-square place-items-center rounded-lg border-2 font-display text-lg font-extrabold sm:text-xl"
                                :class="start === cell ? 'border-ink bg-biru text-white' : (foundCells.includes(cell) ? 'border-ink bg-kuning' : 'border-ink/20 bg-white')"
                                x-text="letter"></button>
                        </template>
                    </div>
                    @break

                @default
                    {{-- Hijaiyah, prophets and the quick manners quiz: multiple choice --}}
                    <div class="flex items-center justify-between gap-3 font-semibold">
                        <p>Soalan <span x-text="index + 1"></span> / <span x-text="questions.length"></span></p>
                        <p x-show="countdown !== null" x-cloak class="rounded-full bg-white px-3 py-1 font-display text-lg font-extrabold" :class="countdown <= 3 && 'text-magenta'">⏱️ <span x-text="countdown"></span></p>
                    </div>
                    <div class="mt-3 {{ $tile }} p-5 text-center">
                        <p class="font-display text-xl leading-snug font-bold" x-text="question?.prompt"></p>
                        <p x-show="question?.big" class="mt-2 text-8xl leading-tight" dir="rtl" lang="ar" x-text="question?.big"></p>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <template x-for="choice in question?.choices ?? []" :key="choice">
                            <button type="button" @click="choose(choice)"
                                class="min-h-14 rounded-2xl border-2 border-ink px-4 py-2 font-display text-lg font-bold shadow-[0_3px_0_var(--color-ink)]"
                                :class="picked === null ? 'bg-white' : (choice === question.answer ? 'bg-kuning' : (choice === picked ? 'bg-magenta text-white' : 'bg-white opacity-60'))"
                                x-text="choice"></button>
                        </template>
                    </div>
            @endswitch
        </div>
    </div>
</x-layouts.child>
