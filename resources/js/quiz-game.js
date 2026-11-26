// Kuiz Interaktif (QuizGameController): one question at a time with a
// timer, big coloured answer tiles, points for speed and streaks. Answers are
// sent to the server, which checks them and keeps the score.
import { sfx } from './games';

const TILE_COLOURS = ['bg-plum', 'bg-magenta', 'bg-biru', 'bg-oren'];
const SOUND_KEY = 'asuh_game_sound';
const CONFETTI_COLOURS = ['#FFDE37', '#C4107A', '#1559D6', '#F79521', '#3A1F6E', '#ffffff'];

const lessMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

// Brand-coloured paper falling over the page, removed when it lands.
const confetti = (count) => {
    if (lessMotion()) return;
    for (let i = 0; i < count; i++) {
        const piece = document.createElement('div');
        piece.className = 'qz-confetti';
        piece.style.left = `${Math.random() * 100}vw`;
        piece.style.background = CONFETTI_COLOURS[i % CONFETTI_COLOURS.length];
        piece.style.animationDelay = `${Math.random() * 0.4}s`;
        piece.style.setProperty('--qz-drift', `${(Math.random() - 0.5) * 30}vw`);
        piece.style.setProperty('--qz-spin', `${360 + Math.random() * 720}deg`);
        piece.style.setProperty('--qz-time', `${1.8 + Math.random() * 1.4}s`);
        document.body.append(piece);
        setTimeout(() => piece.remove(), 3800);
    }
};

export const quizGame = (cfg) => ({
    stage: 'intro', // intro, question, feedback, end
    busy: false,
    error: '',
    questions: [],
    urls: {},
    index: 0,
    score: 0,
    streak: 0,
    order: [],
    typed: '',
    chosen: null,
    result: null,
    summary: null,
    timedOut: false,
    startedAt: 0,
    timeLeftRatio: 1,
    timer: null,
    sound: true,
    shown: 0, // the score as drawn, counting up to the real one
    floatKey: 0, // restarts the floating "+points"
    lastTick: null,
    starsShown: 0,

    init() {
        try { this.sound = localStorage.getItem(SOUND_KEY) !== 'off'; } catch { /* storage unavailable */ }
    },

    get current() {
        return this.questions[this.index] ?? null;
    },

    get hint() {
        return {
            pilihan: 'Pilih satu jawapan',
            betul_salah: 'Betul atau salah?',
            susun: 'Susun ikut tertib yang betul, kemudian Hantar',
            isi: 'Taip jawapan kamu',
        }[this.current?.type] ?? '';
    },

    get stars() {
        const total = this.summary?.total || this.questions.length || 1;
        const ratio = (this.summary?.correct ?? 0) / total;
        return ratio >= 0.9 ? 3 : ratio >= 0.6 ? 2 : ratio > 0 ? 1 : 0;
    },

    toggleSound() {
        this.sound = !this.sound;
        try { localStorage.setItem(SOUND_KEY, this.sound ? 'on' : 'off'); } catch { /* storage unavailable */ }
    },

    play(name) {
        if (this.sound) sfx[name]?.();
    },

    async post(url, body = {}) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    },

    async start() {
        this.busy = true;
        this.error = '';
        try {
            const data = await this.post(cfg.startUrl);
            this.questions = data.questions;
            this.urls = data;
            this.index = 0;
            this.score = 0;
            this.shown = 0;
            this.streak = 0;
            this.summary = null;
            this.play('tap');
            this.ask();
        } catch {
            this.error = 'Kuiz tidak dapat dimulakan. Semak internet dan cuba lagi.';
        } finally {
            this.busy = false;
        }
    },

    restart() {
        this.stage = 'intro';
        this.start();
    },

    ask() {
        const q = this.current;
        this.order = q.type === 'susun' ? [...q.options] : [];
        this.typed = '';
        this.chosen = null;
        this.result = null;
        this.timedOut = false;
        this.stage = 'question';
        this.startedAt = performance.now();
        this.timeLeftRatio = 1;
        this.lastTick = null;
        clearInterval(this.timer);
        this.timer = setInterval(() => {
            const elapsed = (performance.now() - this.startedAt) / 1000;
            const left = 1 - elapsed / q.seconds;
            this.timeLeftRatio = Math.max(0, left);
            // A tick for each of the last three seconds.
            const secondsLeft = Math.ceil(q.seconds - elapsed);
            if (secondsLeft <= 3 && secondsLeft > 0 && secondsLeft !== this.lastTick) {
                this.lastTick = secondsLeft;
                this.play('tap');
            }
            if (left <= 0 && this.stage === 'question') {
                this.timedOut = true;
                this.submit(null);
            }
        }, 100);
        if (q.type === 'isi') this.$nextTick(() => this.$refs.typed?.focus());
    },

    move(i, step) {
        const j = i + step;
        if (j < 0 || j >= this.order.length) return;
        [this.order[i], this.order[j]] = [this.order[j], this.order[i]];
        this.play('flip');
    },

    async submit(answer) {
        if (this.stage !== 'question') return;
        clearInterval(this.timer);
        this.stage = 'feedback';
        this.chosen = answer;
        const ms = Math.round(performance.now() - this.startedAt);
        try {
            this.result = await this.post(this.urls.answerUrl, { question: this.current.id, answer, ms });
            this.score = this.result.score;
            this.streak = this.result.streak;
            this.countUp(this.score);
            this.floatKey++;
            this.play(this.result.correct ? 'right' : 'wrong');
            if (this.result.correct) confetti(this.streak >= 3 ? 50 : 24);
        } catch {
            this.result = { correct: false, points: 0, answer: '', explanation: 'Jawapan tidak dapat dihantar. Semak internet.' };
        }
    },

    async next() {
        if (this.index + 1 < this.questions.length) {
            this.index++;
            this.ask();
            return;
        }
        try {
            this.summary = await this.post(this.urls.finishUrl);
        } catch {
            this.summary = { score: this.score, correct: 0, total: this.questions.length };
        }
        this.stage = 'end';
        this.shown = 0;
        this.countUp(this.summary.score ?? this.score, 1400);
        this.starsShown = 0;
        // Stars appear one at a time, then the confetti.
        for (let i = 1; i <= this.stars; i++) {
            setTimeout(() => { this.starsShown = i; this.play('tap'); }, 350 * i);
        }
        setTimeout(() => {
            this.play(this.stars >= 2 ? 'win' : 'right');
            if (this.stars >= 2) confetti(this.stars === 3 ? 120 : 60);
        }, 350 * (this.stars + 1));
    },

    get secondsLeft() {
        return this.current ? Math.ceil(this.timeLeftRatio * this.current.seconds) : 0;
    },

    // The score counts up to its new value instead of jumping.
    countUp(target, duration = 600) {
        const from = this.shown;
        if (lessMotion() || target === from) { this.shown = target; return; }
        const start = performance.now();
        const step = (now) => {
            const t = Math.min(1, (now - start) / duration);
            this.shown = Math.round(from + (target - from) * (1 - (1 - t) ** 3));
            if (t < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    },

    tileColour(i) {
        return TILE_COLOURS[i % TILE_COLOURS.length];
    },

    // After answering: the right tile stays bright, a wrong pick is crossed.
    feedbackClass(value) {
        if (this.stage !== 'feedback' || !this.result) return '';
        const q = this.current;
        const right = q.type === 'pilihan'
            ? q.options[value] === this.result.answer
            : (value === true) === (this.result.answer === 'Betul');
        if (right) return 'ring-4 ring-kuning ring-offset-2 scale-[1.02]';
        return value === this.chosen ? 'opacity-60 line-through' : 'opacity-40';
    },
});
