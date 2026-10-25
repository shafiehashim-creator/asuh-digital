import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/reader.js', 'resources/js/page-prep.js'],
            refresh: true,
            fonts: [
                bunny('Baloo 2', {
                    weights: [600, 700, 800],
                }),
                bunny('Nunito', {
                    weights: [400, 600, 700, 800],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
