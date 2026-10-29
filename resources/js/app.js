// Let Vite fingerprint the brand images so Blade can use Vite::asset().
// Exported so the bundler does not tree-shake the glob away. Only the
// web-sized WebP files are published; the PNGs are kept as source art.
export const images = import.meta.glob(['../images/**/*.webp']);

// Alpine.js for small interactive bits on public pages (CLAUDE.md stack):
// the music video's play button, the affiliate copy buttons and the
// "Pasang app" buttons and the campaign countdowns.
import Alpine from 'alpinejs';
import { installApp } from './install';
import { countdown } from './countdown';
import { registerGames } from './games';

window.Alpine = Alpine;
Alpine.data('installApp', installApp);
Alpine.data('countdown', countdown);
registerGames(Alpine);

// A row of filter chips swiped sideways on phones: the right edge fades only
// while more chips are hidden there, and the chosen chip is scrolled into view.
Alpine.data('chipRow', () => ({
    more: false,

    init() {
        this.$el.querySelector('[aria-current]')?.scrollIntoView({ inline: 'center', block: 'nearest' });
        this.check();
        this.$el.addEventListener('scroll', () => this.check(), { passive: true });
        window.addEventListener('resize', () => this.check());
    },

    check() {
        const el = this.$el;
        this.more = el.scrollLeft + el.clientWidth < el.scrollWidth - 4;
    },
}));
Alpine.start();
