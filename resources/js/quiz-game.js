// Kuiz Interaktif (QuizGameController): one question at a time with a
// timer, big coloured answer tiles, points for speed and streaks. Answers are
// sent to the server, which checks them and keeps the score.
import { sfx } from './games';

const TILE_COLOURS = ['bg-plum', 'bg-magenta', 'bg-biru', 'bg-oren'];
const SOUND_KEY = 'asuh_game_sound';

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
        clearInterval(this.timer);
        this.timer = setInterval(() => {
            const left = 1 - (performance.now() - this.startedAt) / (q.seconds * 1000);
            this.timeLeftRatio = Math.max(0, left);
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
            this.play(this.result.correct ? 'right' : 'wrong');
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
        this.play(this.stars >= 2 ? 'win' : 'right');
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
