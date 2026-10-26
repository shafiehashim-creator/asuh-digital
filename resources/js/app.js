// Let Vite fingerprint the brand images so Blade can use Vite::asset().
// Exported so the bundler does not tree-shake the glob away. Only the
// web-sized WebP files are published; the PNGs are kept as source art.
export const images = import.meta.glob(['../images/**/*.webp']);

// Alpine.js for small interactive bits on public pages (CLAUDE.md stack):
// the music video's play button, the affiliate copy buttons and the
// "Pasang app" buttons.
import Alpine from 'alpinejs';
import { installApp } from './install';

window.Alpine = Alpine;
Alpine.data('installApp', installApp);
Alpine.start();
