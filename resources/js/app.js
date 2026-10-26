// Let Vite fingerprint the brand images so Blade can use Vite::asset().
// Exported so the bundler does not tree-shake the glob away. Only the
// web-sized WebP files are published; the PNGs are kept as source art.
export const images = import.meta.glob(['../images/**/*.webp']);

// Alpine.js for small interactive bits on public pages (CLAUDE.md stack):
// the music video's play button and the affiliate copy buttons.
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
