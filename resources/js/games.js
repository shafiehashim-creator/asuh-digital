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

/*
 * Sound effects made in the browser with the Web Audio API (no sound files):
 * short tones for a tap, a right and a wrong answer, and a little tune when a
 * round is won. Muted with the 🔊 button; the choice is kept on the device.
 */
const SOUND_KEY = 'asuh_game_sound';
let audio = null;

const soundOn = () => {
    try {
        return localStorage.getItem(SOUND_KEY) !== 'off';
    } catch {
        return true;
    }
};

const play = (notes) => {
    if (!soundOn()) {
        return;
    }
    try {
        audio ??= new (window.AudioContext || window.webkitAudioContext)();
        if (audio.state === 'suspended') {
            audio.resume();
        }
        const now = audio.currentTime;
        for (const [freq, start, length, type = 'sine', volume = 0.18] of notes) {
            const osc = audio.createOscillator();
            const gain = audio.createGain();
            osc.type = type;
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.0001, now + start);
            gain.gain.exponentialRampToValueAtTime(volume, now + start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + start + length);
            osc.connect(gain).connect(audio.destination);
            osc.start(now + start);
            osc.stop(now + start + length + 0.05);
        }
    } catch {
        // No sound on this device: the game plays on silently.
    }
};

export const sfx = {
    tap: () => play([[520, 0, 0.07, 'triangle', 0.12]]),
    flip: () => play([[420, 0, 0.06, 'triangle', 0.1], [640, 0.05, 0.06, 'triangle', 0.1]]),
    right: () => play([[660, 0, 0.12], [880, 0.1, 0.22]]),
    wrong: () => play([[260, 0, 0.18, 'triangle'], [200, 0.14, 0.26, 'triangle']]),
    win: () => play([[523, 0, 0.14], [659, 0.14, 0.14], [784, 0.28, 0.14], [1047, 0.42, 0.4]]),
    levelUp: () => play([[784, 0, 0.12], [988, 0.12, 0.12], [1175, 0.24, 0.12], [1568, 0.36, 0.45]]),
};

/*
 * Questions not seen lately come first, so a child playing again meets new
 * ones before old ones come back. Remembered per game and level on the device.
 */
const SEEN_LIMIT = 80;

const seenKey = (cfg) => `asuh_seen_${cfg.game}_${cfg.level ?? 1}`;

const readSeen = (cfg) => {
    try {
        return JSON.parse(localStorage.getItem(seenKey(cfg)) || '[]');
    } catch {
        return [];
    }
};

const freshFirst = (cfg, list, key) => {
    const seen = readSeen(cfg);
    // Never seen first, then the ones seen longest ago.
    const rank = (item) => {
        const at = seen.indexOf(key(item));

        return at === -1 ? Infinity : at;
    };

    return shuffle(list).sort((a, b) => rank(b) - rank(a));
};

const markSeen = (cfg, keys) => {
    try {
        const next = [...keys, ...readSeen(cfg).filter((k) => !keys.includes(k))].slice(0, SEEN_LIMIT);
        localStorage.setItem(seenKey(cfg), JSON.stringify(next));
    } catch {
        // Private browsing: questions are still shuffled.
    }
};

// Copies the shell in with its getters intact (a spread would freeze them).
const withShell = (cfg, game) => Object.defineProperties(game, Object.getOwnPropertyDescriptors(shell(cfg)));

const shell = (cfg) => ({
    level: cfg.level ?? 1,
    unlocked: cfg.unlocked ?? 1,
    newLevel: false,
    sound: soundOn(),
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
                body: JSON.stringify({ seconds, score, level: this.level }),
            });
            if (response.ok) {
                const data = await response.json();
                this.secondsLeft = data.seconds_left;
                if (data.unlocked > this.unlocked) {
                    this.unlocked = data.unlocked;
                    this.newLevel = true;
                    setTimeout(() => sfx.levelUp(), 700);
                }
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
        this.newLevel = false;
        this.score >= 60 ? sfx.win() : sfx.right();
        this.tick(this.score);
    },

    toggleSound() {
        this.sound = !this.sound;
        try {
            localStorage.setItem(SOUND_KEY, this.sound ? 'on' : 'off');
        } catch {
            // Private browsing: the choice lasts for this page only.
        }
        if (this.sound) {
            sfx.tap();
        }
    },

    get stars() {
        return this.score >= 90 ? 3 : this.score >= 60 ? 2 : 1;
    },

    get minutesLeft() {
        return this.secondsLeft === null ? null : Math.ceil(this.secondsLeft / 60);
    },
});

// 🃏 Match each picture to its word (Sukar: pairs that need knowing).
const gameMemory = (cfg) => withShell(cfg, {
    cards: [],
    open: [],
    matched: [],
    moves: 0,
    busy: false,
    peeking: false,

    init() {
        this.startClock();
        this.restart();
    },

    restart() {
        const pairs = freshFirst(cfg, cfg.content.pairs, (pair) => pair[1]).slice(0, cfg.content.count);
        markSeen(cfg, pairs.map((pair) => pair[1]));
        this.cards = shuffle(pairs.flatMap(([first, second], id) => [
            { key: `${id}a`, id, face: first, word: cfg.content.words ?? false },
            { key: `${id}b`, id, face: second, word: true },
        ]));
        this.open = [];
        this.matched = [];
        this.moves = 0;
        this.done = false;
        // Mudah shows every card for a moment first.
        if (cfg.content.preview) {
            this.peeking = true;
            setTimeout(() => (this.peeking = false), cfg.content.preview * 1000);
        }
    },

    isUp(card) {
        return this.peeking || this.open.includes(card.key) || this.matched.includes(card.id);
    },

    flip(card) {
        if (this.busy || this.peeking || this.isUp(card) || this.timeUp) {
            return;
        }
        this.open.push(card.key);
        sfx.flip();
        if (this.open.length < 2) {
            return;
        }

        this.moves++;
        const [a, b] = this.open.map((key) => this.cards.find((c) => c.key === key));
        if (a.id === b.id) {
            this.matched.push(a.id);
            this.open = [];
            sfx.right();
            if (this.matched.length === this.cards.length / 2) {
                this.finish(100 - (this.moves - this.cards.length / 2) * 5);
            }
        } else {
            this.busy = true;
            setTimeout(() => sfx.wrong(), 350);
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
        this.items = freshFirst(cfg, cfg.content.scenarios, (item) => item.text).slice(0, cfg.content.rounds);
        markSeen(cfg, this.items.map((item) => item.text));
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
        ok ? sfx.right() : sfx.wrong();
    },

    next() {
        this.feedback = null;
        this.index++;
        if (this.index >= this.items.length) {
            this.finish((this.correct / this.items.length) * 100);
        }
    },
});

// 🔢 Tap the items in their right order, set after set. Sederhana hides a
// stranger from another set among them; Sukar also asks for the order backwards.
const gameOrder = (cfg) => withShell(cfg, {
    sets: [],
    setIndex: 0,
    tiles: [],
    placed: [],
    mistakes: 0,
    shaking: null,
    setDone: false,
    impostor: null,
    reverse: cfg.content.reverse ?? false,

    init() {
        this.startClock();
        this.restart();
    },

    restart() {
        this.sets = freshFirst(cfg, cfg.content.sets, (set) => set.title).slice(0, cfg.content.rounds);
        markSeen(cfg, this.sets.map((set) => set.title));
        this.setIndex = 0;
        this.mistakes = 0;
        this.done = false;
        this.load();
    },

    get set() {
        return this.sets[this.setIndex];
    },

    load() {
        const tiles = this.set.items.map((text, order) => ({ text, order }));
        this.impostor = null;
        if (cfg.content.impostor) {
            const stranger = shuffle(cfg.content.sets.filter((set) => set.title !== this.set.title).flatMap((set) => set.items))
                .find((text) => !this.set.items.includes(text));
            if (stranger) {
                this.impostor = stranger;
                tiles.push({ text: stranger, order: -1 });
            }
        }
        this.tiles = shuffle(tiles);
        this.placed = [];
        this.setDone = false;
    },

    // The position the next tap must be.
    get expected() {
        return this.reverse ? this.set.items.length - 1 - this.placed.length : this.placed.length;
    },

    tap(tile) {
        if (this.setDone || this.timeUp || this.placed.includes(tile.order)) {
            return;
        }
        if (tile.order === this.expected) {
            this.placed.push(tile.order);
            if (this.placed.length === this.set.items.length) {
                this.setDone = true;
                sfx.right();
            } else {
                sfx.tap();
            }
        } else {
            this.mistakes++;
            sfx.wrong();
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

// ب 🌙 ⏱️ Multiple choice for most games. Questions may keep their choices in
// order (a story, signs, groups), ask for two numbers that make a target, or
// explain the answer afterwards. Lives end the round after three misses, and
// survival keeps asking until then.
const gameQuiz = (cfg) => withShell(cfg, {
    questions: [],
    index: 0,
    correct: 0,
    picked: null,
    lastOk: null,
    selection: [],
    timer: null,
    countdown: null,
    lives: null,
    maxLives: cfg.content.lives ?? null,
    survival: cfg.content.survival ?? null,

    init() {
        this.startClock();
        this.restart();
    },

    restart() {
        const key = (q) => `${q.prompt}|${q.big ?? ''}|${q.answer}`;
        const pool = freshFirst(cfg, cfg.content.questions, key);
        const count = this.survival ? pool.length : Math.min(cfg.content.rounds ?? pool.length, pool.length);
        this.questions = pool.slice(0, count).map((q) => ({ ...q, choices: this.choicesFor(q) }));
        markSeen(cfg, this.questions.slice(0, cfg.content.rounds ?? 15).map(key));
        this.index = 0;
        this.correct = 0;
        this.picked = null;
        this.lastOk = null;
        this.selection = [];
        this.lives = this.maxLives;
        this.done = false;
        this.startTimer();
    },

    choicesFor(q) {
        if (q.keepOrder || q.pick) {
            return [...new Set(q.options)];
        }
        // Up to 3 other choices, never one with the same name as the answer.
        const others = shuffle([...new Set(q.options)].filter((o) => o !== q.answer)).slice(0, 3);

        return shuffle([q.answer, ...others]);
    },

    get question() {
        return this.questions[this.index];
    },

    get hearts() {
        return this.maxLives === null ? '' : '❤️'.repeat(this.lives) + '🤍'.repeat(this.maxLives - this.lives);
    },

    startTimer() {
        clearInterval(this.timer);
        this.countdown = null;
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
                this.settle(false, null);
            }
        }, 1000);
    },

    choose(choice) {
        if (this.picked !== null || this.timeUp || this.done) {
            return;
        }
        const q = this.question;
        if (q.pick) {
            if (this.selection.includes(choice)) {
                this.selection = this.selection.filter((c) => c !== choice);

                return;
            }
            this.selection.push(choice);
            sfx.tap();
            if (this.selection.length < q.pick) {
                return;
            }
            const [a, b] = this.selection.map(Number);

            return this.settle(q.op === 'times' ? a * b === q.target : a + b === q.target, 'pick');
        }

        this.settle(choice === q.answer, choice);
    },

    settle(ok, choice) {
        if (this.picked !== null) {
            return;
        }
        clearInterval(this.timer);
        this.picked = choice ?? '';
        this.lastOk = ok;
        if (ok) {
            this.correct++;
            sfx.right();
        } else {
            sfx.wrong();
            if (this.lives !== null) {
                this.lives--;
            }
        }
        const q = this.question;
        // Time to read why, or the right pair.
        const wait = q.why || (q.pick && !ok) ? 2800 : ok ? 700 : 1600;
        setTimeout(() => this.next(), wait);
    },

    next() {
        this.picked = null;
        this.lastOk = null;
        this.selection = [];
        this.index++;
        if (this.lives === 0 || this.index >= this.questions.length) {
            this.finish(this.survival ? (this.correct / this.survival) * 100 : (this.correct / this.questions.length) * 100);
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

        for (const word of freshFirst(cfg, cfg.content.words.filter((w) => w.length <= size), (w) => w)) {
            if (placed.length === cfg.content.count) {
                break;
            }
            for (let attempt = 0; attempt < 200; attempt++) {
                const [dr, dc] = directions[Math.floor(Math.random() * directions.length)];
                const row = Math.floor(Math.random() * (size - dr * (word.length - 1)));
                const col = Math.floor(Math.random() * (size - dc * (word.length - 1)));
                // At the hardest level some words hide backwards.
                const letters = cfg.content.backwards && Math.random() < 0.5 ? [...word].reverse() : [...word];
                const cells = letters.map((_, i) => (row + dr * i) * size + (col + dc * i));
                if (cells.every((cell, i) => grid[cell] === null || grid[cell] === letters[i])) {
                    cells.forEach((cell, i) => (grid[cell] = letters[i]));
                    placed.push(word);
                    break;
                }
            }
        }

        const filler = 'ABDEGHIJKLMNOPRSTUWYZ';
        this.grid = grid.map((letter) => letter ?? filler[Math.floor(Math.random() * filler.length)]);
        this.words = placed;
        markSeen(cfg, placed);
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
            sfx.tap();

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
            sfx.right();
            if (this.found.length === this.words.length) {
                this.finish(100 - this.misses * 5);
            }
        } else {
            this.misses++;
            sfx.wrong();
        }
        this.start = null;
    },
});

// 🖼️ Slide the pieces into place until the picture of Anis and Anas is whole.
const gameSliding = (cfg) => withShell(cfg, {
    size: cfg.content.size,
    tiles: [],
    moves: 0,
    image: null,

    init() {
        this.startClock();
        this.restart();
    },

    restart() {
        const n = this.size * this.size;
        this.image = cfg.content.images[Math.floor(Math.random() * cfg.content.images.length)];
        // Shuffle by random legal moves from the solved picture, so it can always be solved.
        const tiles = Array.from({ length: n }, (_, i) => i);
        let blank = n - 1;
        let previous = -1;
        for (let i = 0; i < cfg.content.shuffle; i++) {
            const next = shuffle(this.neighbours(blank)).find((cell) => cell !== previous);
            [tiles[blank], tiles[next]] = [tiles[next], tiles[blank]];
            previous = blank;
            blank = next;
        }
        if (tiles.every((tile, i) => tile === i)) {
            const next = this.neighbours(blank)[0];
            [tiles[blank], tiles[next]] = [tiles[next], tiles[blank]];
        }
        this.tiles = tiles;
        this.moves = 0;
        this.done = false;
    },

    neighbours(cell) {
        const size = this.size;
        const [row, col] = [Math.floor(cell / size), cell % size];

        return [[row - 1, col], [row + 1, col], [row, col - 1], [row, col + 1]]
            .filter(([r, c]) => r >= 0 && r < size && c >= 0 && c < size)
            .map(([r, c]) => r * size + c);
    },

    blank() {
        return this.tiles.indexOf(this.size * this.size - 1);
    },

    isBlank(tile) {
        return tile === this.size * this.size - 1;
    },

    // Where a piece of the picture sits, as a CSS background.
    pieceStyle(tile) {
        const size = this.size;
        const [row, col] = [Math.floor(tile / size), tile % size];
        const at = (i) => (size === 1 ? 0 : (i / (size - 1)) * 100);

        return `background-image:url('${this.image}');background-size:${size * 100}% ${size * 100}%;background-position:${at(col)}% ${at(row)}%`;
    },

    slide(cell) {
        if (this.done || this.timeUp || !this.neighbours(cell).includes(this.blank())) {
            return;
        }
        const blank = this.blank();
        [this.tiles[blank], this.tiles[cell]] = [this.tiles[cell], this.tiles[blank]];
        this.moves++;
        sfx.tap();
        if (this.tiles.every((tile, i) => tile === i)) {
            // Full marks within about one and a half times the shuffle, less after that.
            const fair = cfg.content.shuffle * 1.5 + 10;
            setTimeout(() => this.finish(Math.max(40, Math.min(100, (fair / this.moves) * 100))), 300);
        }
    },
});

// 🔲 Fill the grid so every row, column and box has each picture once.
const gameSudoku = (cfg) => withShell(cfg, {
    size: cfg.content.size,
    symbols: cfg.content.symbols.slice(0, cfg.content.size),
    solution: [],
    given: [],
    entries: [],
    selected: null,
    mistakes: 0,
    shaking: null,
    checkLater: cfg.content.checkLater ?? false,
    wrongCells: [],

    init() {
        this.startClock();
        this.restart();
    },

    restart() {
        this.wrongCells = [];
        const n = this.size;
        // Boxes are 2×2 for a 4×4 grid and 2 rows × 3 columns for 6×6.
        const [boxRows, boxCols] = n === 6 ? [2, 3] : [2, 2];
        const pattern = (r, c) => (boxCols * (r % boxRows) + Math.floor(r / boxRows) + c) % n;
        // Shuffle the symbols, the rows within each band and the bands, the same for columns.
        const order = (count, group) => shuffle([...Array(count / group).keys()]).flatMap((g) => shuffle([...Array(group).keys()]).map((i) => g * group + i));
        const rows = order(n, boxRows);
        const cols = order(n, boxCols);
        const symbols = shuffle([...Array(n).keys()]);

        this.solution = rows.flatMap((r) => cols.map((c) => symbols[pattern(r, c)]));
        // Hide cells one by one, keeping only those that leave a single answer.
        const hidden = new Set();
        const grid = [...this.solution];
        for (const cell of shuffle([...Array(n * n).keys()])) {
            if (hidden.size === cfg.content.blanks) {
                break;
            }
            grid[cell] = null;
            if (this.solutions(grid, boxRows, boxCols) === 1) {
                hidden.add(cell);
            } else {
                grid[cell] = this.solution[cell];
            }
        }
        this.given = this.solution.map((_, cell) => !hidden.has(cell));
        this.entries = this.solution.map((value, cell) => (hidden.has(cell) ? null : value));
        this.selected = [...hidden][0];
        this.mistakes = 0;
        this.done = false;
    },

    // Counts the ways to fill the grid, stopping at two.
    solutions(grid, boxRows, boxCols) {
        const n = this.size;
        const empty = grid.indexOf(null);
        if (empty === -1) {
            return 1;
        }
        const [row, col] = [Math.floor(empty / n), empty % n];
        const [top, left] = [row - (row % boxRows), col - (col % boxCols)];
        let count = 0;
        for (let value = 0; value < n && count < 2; value++) {
            let clash = false;
            for (let i = 0; i < n && !clash; i++) {
                const boxCell = (top + Math.floor(i / boxCols)) * n + left + (i % boxCols);
                clash = grid[row * n + i] === value || grid[i * n + col] === value || grid[boxCell] === value;
            }
            if (!clash) {
                grid[empty] = value;
                count += this.solutions(grid, boxRows, boxCols);
                grid[empty] = null;
            }
        }

        return count;
    },

    borderClass(cell) {
        const n = this.size;
        const [boxRows, boxCols] = n === 6 ? [2, 3] : [2, 2];
        const [row, col] = [Math.floor(cell / n), cell % n];

        return [
            col % boxCols === boxCols - 1 && col < n - 1 ? 'border-r-ink border-r-4' : '',
            row % boxRows === boxRows - 1 && row < n - 1 ? 'border-b-ink border-b-4' : '',
        ].join(' ');
    },

    pick(cell) {
        // In Sukar a filled square can be changed until the grid is checked.
        if (!this.given[cell] && (this.entries[cell] === null || this.checkLater)) {
            this.selected = cell;
            sfx.tap();
        }
    },

    get full() {
        return this.entries.every((entry) => entry !== null);
    },

    // Sukar: check the whole grid; wrong squares flash and empty again.
    check() {
        if (!this.full || this.done) {
            return;
        }
        this.wrongCells = this.entries.map((entry, cell) => (entry !== this.solution[cell] ? cell : -1)).filter((cell) => cell !== -1);
        if (this.wrongCells.length === 0) {
            sfx.right();
            setTimeout(() => this.finish(100 - this.mistakes * 15), 300);

            return;
        }
        this.mistakes++;
        sfx.wrong();
        setTimeout(() => {
            this.wrongCells.forEach((cell) => (this.entries[cell] = null));
            this.selected = this.wrongCells[0];
            this.wrongCells = [];
        }, 1200);
    },

    place(value) {
        const cell = this.selected;
        if (cell === null || this.done || this.timeUp) {
            return;
        }
        if (this.checkLater) {
            this.entries[cell] = value;
            sfx.tap();
            const next = this.entries.findIndex((entry) => entry === null);
            this.selected = next === -1 ? cell : next;

            return;
        }
        if (this.solution[cell] !== value) {
            this.mistakes++;
            this.shaking = value;
            sfx.wrong();
            setTimeout(() => (this.shaking = null), 500);

            return;
        }
        this.entries[cell] = value;
        sfx.right();
        const next = this.entries.findIndex((entry) => entry === null);
        this.selected = next === -1 ? null : next;
        if (next === -1) {
            setTimeout(() => this.finish(100 - this.mistakes * 10), 300);
        }
    },
});

export function registerGames(Alpine) {
    Alpine.data('gameSliding', gameSliding);
    Alpine.data('gameSudoku', gameSudoku);
    Alpine.data('gameMemory', gameMemory);
    Alpine.data('gameGoodChoice', gameGoodChoice);
    Alpine.data('gameOrder', gameOrder);
    Alpine.data('gameQuiz', gameQuiz);
    Alpine.data('gameWordSearch', gameWordSearch);
}
