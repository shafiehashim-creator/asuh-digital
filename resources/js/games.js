/*
 * Games in Mod Anak (2026-10-27). Each game is an Alpine component fed
 * with its content from the server (App\Support\GameContent). The shared
 * shell counts the time played (a heartbeat every 30 seconds while the page
 * is visible, as for videos) against the parent's daily limit, and sends
 * the score when a round ends.
 */

const shuffle = (list) => {
    const out = [...list];
    for (let i = out.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [out[i], out[j]] = [out[j], out[i]];
    }

    return out;
};

// Copies the shell in with its getters intact (a spread would freeze them).
const withShell = (cfg, game) => Object.defineProperties(game, Object.getOwnPropertyDescriptors(shell(cfg)));

const shell = (cfg) => ({
    secondsLeft: cfg.secondsLeft,
    timeUp: cfg.secondsLeft !== null && cfg.secondsLeft <= 0,
    done: false,
    score: 0,
    lastTick: Date.now(),

    startClock() {
        this.clock = setInterval(() => {
            if (!document.hidden && !this.timeUp) {
                this.tick(null);
            }
        }, 30000);
    },

    async tick(score) {
        const now = Date.now();
        const seconds = Math.min(600, Math.round((now - this.lastTick) / 1000));
        this.lastTick = now;

        try {
            const response = await fetch(cfg.progressUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                body: JSON.stringify({ seconds, score }),
            });
            if (response.ok) {
                const data = await response.json();
                this.secondsLeft = data.seconds_left;
                if (data.seconds_left === 0) {
                    this.timeUp = true;
                    clearInterval(this.clock);
                }
            }
        } catch {
            // Offline for a moment: the next heartbeat carries the time.
        }
    },

    finish(score) {
        this.score = Math.max(0, Math.min(100, Math.round(score)));
        this.done = true;
        this.tick(this.score);
    },

    get stars() {
        return this.score >= 90 ? 3 : this.score >= 60 ? 2 : 1;
    },

    get minutesLeft() {
        return this.secondsLeft === null ? null : Math.ceil(this.secondsLeft / 60);
    },
});

// 🃏 Match each picture to its word.
const gameMemory = (cfg) => withShell(cfg, {
    cards: [],
    open: [],
    matched: [],
    moves: 0,
    busy: false,

    init() {
        this.startClock();
        this.restart();
    },

    restart() {
        this.cards = shuffle(shuffle(cfg.content.pairs).slice(0, cfg.content.count).flatMap(([emoji, word], id) => [
            { key: `${id}e`, id, face: emoji, word: false },
            { key: `${id}w`, id, face: word, word: true },
        ]));
        this.open = [];
        this.matched = [];
        this.moves = 0;
        this.done = false;
    },

    isUp(card) {
        return this.open.includes(card.key) || this.matched.includes(card.id);
    },

    flip(card) {
        if (this.busy || this.isUp(card) || this.timeUp) {
            return;
        }
        this.open.push(card.key);
        if (this.open.length < 2) {
            return;
        }

        this.moves++;
        const [a, b] = this.open.map((key) => this.cards.find((c) => c.key === key));
        if (a.id === b.id) {
            this.matched.push(a.id);
            this.open = [];
            if (this.matched.length === this.cards.length / 2) {
                this.finish(100 - (this.moves - this.cards.length / 2) * 5);
            }
        } else {
            this.busy = true;
            setTimeout(() => {
                this.open = [];
                this.busy = false;
            }, 900);
        }
    },
});

// 😇 Good or not good? Anis and Anas against the tempters.
const gameGoodChoice = (cfg) => withShell(cfg, {
    items: [],
    index: 0,
    correct: 0,
    feedback: null,

    init() {
        this.startClock();
        this.restart();
    },

    restart() {
        this.items = shuffle(cfg.content.scenarios).slice(0, cfg.content.rounds);
        this.index = 0;
        this.correct = 0;
        this.feedback = null;
        this.done = false;
    },

    get item() {
        return this.items[this.index];
    },

    answer(good) {
        if (this.feedback || this.timeUp) {
            return;
        }
        const ok = this.item.good === good;
        this.correct += ok ? 1 : 0;
        this.feedback = { ok, why: this.item.why };
    },

    next() {
        this.feedback = null;
        this.index++;
        if (this.index >= this.items.length) {
            this.finish((this.correct / this.items.length) * 100);
        }
    },
});

// 🔢 Tap the items in their right order, set after set.
const gameOrder = (cfg) => withShell(cfg, {
    sets: [],
    setIndex: 0,
    tiles: [],
    placed: [],
    mistakes: 0,
    shaking: null,
    setDone: false,

    init() {
        this.startClock();
        this.restart();
    },

    restart() {
        this.sets = shuffle(cfg.content.sets).slice(0, cfg.content.rounds);
        this.setIndex = 0;
        this.mistakes = 0;
        this.done = false;
        this.load();
    },

    get set() {
        return this.sets[this.setIndex];
    },

    load() {
        this.tiles = shuffle(this.set.items.map((text, order) => ({ text, order })));
        this.placed = [];
        this.setDone = false;
    },

    tap(tile) {
        if (this.setDone || this.timeUp || this.placed.includes(tile.order)) {
            return;
        }
        if (tile.order === this.placed.length) {
            this.placed.push(tile.order);
            if (this.placed.length === this.set.items.length) {
                this.setDone = true;
            }
        } else {
            this.mistakes++;
            this.shaking = tile.order;
            setTimeout(() => (this.shaking = null), 500);
        }
    },

    nextSet() {
        this.setIndex++;
        if (this.setIndex >= this.sets.length) {
            this.finish(100 - this.mistakes * 8);
        } else {
            this.load();
        }
    },
});

// ب 🌙 ⏱️ Multiple choice: hijaiyah letters, prophets, quick manners quiz.
const gameQuiz = (cfg) => withShell(cfg, {
    questions: [],
    index: 0,
    correct: 0,
    picked: null,
    timer: null,
    countdown: null,

    init() {
        this.startClock();
        this.restart();
    },

    restart() {
        this.questions = shuffle(cfg.content.questions).slice(0, cfg.content.rounds).map((q) => {
            // Up to 3 other choices, never one with the same name as the answer.
            const others = shuffle([...new Set(q.options)].filter((o) => o !== q.answer)).slice(0, 3);

            return { ...q, choices: shuffle([q.answer, ...others]) };
        });
        this.index = 0;
        this.correct = 0;
        this.picked = null;
        this.done = false;
        this.startTimer();
    },

    get question() {
        return this.questions[this.index];
    },

    startTimer() {
        clearInterval(this.timer);
        if (!cfg.content.seconds) {
            return;
        }
        this.countdown = cfg.content.seconds;
        this.timer = setInterval(() => {
            if (this.timeUp) {
                clearInterval(this.timer);

                return;
            }
            this.countdown--;
            if (this.countdown <= 0) {
                this.choose(null);
            }
        }, 1000);
    },

    choose(choice) {
        if (this.picked !== null || this.timeUp) {
            return;
        }
        clearInterval(this.timer);
        this.picked = choice ?? '';
        this.correct += choice === this.question.answer ? 1 : 0;
        setTimeout(() => this.next(), choice === this.question.answer ? 700 : 1600);
    },

    next() {
        this.picked = null;
        this.index++;
        if (this.index >= this.questions.length) {
            this.finish((this.correct / this.questions.length) * 100);
        } else {
            this.startTimer();
        }
    },
});

// 🔎 Find the hidden words: tap the first letter, then the last.
const gameWordSearch = (cfg) => withShell(cfg, {
    size: cfg.content.size,
    grid: [],
    words: [],
    found: [],
    foundCells: [],
    start: null,
    misses: 0,

    init() {
        this.startClock();
        this.restart();
    },

    restart() {
        const size = this.size;
        const directions = cfg.content.diagonal ? [[0, 1], [1, 0], [1, 1]] : [[0, 1], [1, 0]];
        const grid = Array(size * size).fill(null);
        const placed = [];

        for (const word of shuffle(cfg.content.words.filter((w) => w.length <= size))) {
            if (placed.length === cfg.content.count) {
                break;
            }
            for (let attempt = 0; attempt < 200; attempt++) {
                const [dr, dc] = directions[Math.floor(Math.random() * directions.length)];
                const row = Math.floor(Math.random() * (size - dr * (word.length - 1)));
                const col = Math.floor(Math.random() * (size - dc * (word.length - 1)));
                const cells = [...word].map((_, i) => (row + dr * i) * size + (col + dc * i));
                if (cells.every((cell, i) => grid[cell] === null || grid[cell] === word[i])) {
                    cells.forEach((cell, i) => (grid[cell] = word[i]));
                    placed.push(word);
                    break;
                }
            }
        }

        const letters = 'ABDEGHIJKLMNOPRSTUWYZ';
        this.grid = grid.map((letter) => letter ?? letters[Math.floor(Math.random() * letters.length)]);
        this.words = placed;
        this.found = [];
        this.foundCells = [];
        this.start = null;
        this.misses = 0;
        this.done = false;
    },

    tapCell(cell) {
        if (this.timeUp || this.done) {
            return;
        }
        if (this.start === null) {
            this.start = cell;

            return;
        }
        if (this.start === cell) {
            this.start = null;

            return;
        }

        const size = this.size;
        const [r1, c1, r2, c2] = [Math.floor(this.start / size), this.start % size, Math.floor(cell / size), cell % size];
        const dr = Math.sign(r2 - r1);
        const dc = Math.sign(c2 - c1);
        const length = Math.max(Math.abs(r2 - r1), Math.abs(c2 - c1)) + 1;
        const straight = r1 === r2 || c1 === c2 || Math.abs(r2 - r1) === Math.abs(c2 - c1);
        const cells = straight ? Array.from({ length }, (_, i) => (r1 + dr * i) * size + (c1 + dc * i)) : [];
        const text = cells.map((c) => this.grid[c]).join('');
        const word = this.words.find((w) => !this.found.includes(w) && (w === text || w === [...text].reverse().join('')));

        if (word) {
            this.found.push(word);
            this.foundCells.push(...cells);
            if (this.found.length === this.words.length) {
                this.finish(100 - this.misses * 5);
            }
        } else {
            this.misses++;
        }
        this.start = null;
    },
});

export function registerGames(Alpine) {
    Alpine.data('gameMemory', gameMemory);
    Alpine.data('gameGoodChoice', gameGoodChoice);
    Alpine.data('gameOrder', gameOrder);
    Alpine.data('gameQuiz', gameQuiz);
    Alpine.data('gameWordSearch', gameWordSearch);
}
