// "Versi pantas" (admin): renders every PDF page to a light image in this
// browser and uploads it, so readers on phones get small images instead of
// print-resolution PDF pages. See App\Http\Controllers\Admin\PageImageController.
import { GlobalWorkerOptions, getDocument, Util } from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

GlobalWorkerOptions.workerSrc = workerUrl;

const MAX_UPLOADS = 3; // uploads in flight while the next pages render

const toBlob = (canvas, type, quality) => new Promise((resolve) => canvas.toBlob(resolve, type, quality));

// Audiobook word highlight: every word printed on the page with its box, as
// fractions of the page. A PDF whose text was turned into pictures has none.
const measurer = document.createElement('canvas').getContext('2d');

const pageWords = async (page) => {
    const viewport = page.getViewport({ scale: 1 });
    const content = await page.getTextContent();
    const words = [];
    for (const item of content.items) {
        if (!item.str || !item.str.trim()) continue;
        const tx = Util.transform(viewport.transform, item.transform);
        const height = Math.hypot(tx[2], tx[3]);
        if (Math.abs(tx[1]) > 0.01) continue; // rotated text is left out
        // Measure each piece in a similar font, then scale to the run's real width.
        const family = content.styles[item.fontName]?.fontFamily || 'sans-serif';
        measurer.font = `${height}px ${family}`;
        const total = measurer.measureText(item.str).width || 1;
        const scale = item.width / total;
        let before = '';
        for (const part of item.str.split(/(\s+)/)) {
            if (part.trim()) {
                words.push({
                    t: part,
                    x: +((tx[4] + measurer.measureText(before).width * scale) / viewport.width).toFixed(4),
                    y: +((tx[5] - height) / viewport.height).toFixed(4),
                    w: +((measurer.measureText(part).width * scale) / viewport.width).toFixed(4),
                    h: +(height / viewport.height).toFixed(4),
                });
            }
            before += part;
        }
    }
    return words;
};

const setup = (item) => {
    const status = item.querySelector('[data-prep-status]');
    const bar = item.querySelector('[data-prep-bar]');
    const fill = item.querySelector('[data-prep-fill]');
    const button = item.querySelector('[data-prep-run]');
    const csrf = item.dataset.csrf;
    const width = parseInt(item.dataset.width, 10);

    const post = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body,
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response;
    };

    const upload = async (page, blob, extension, words) => {
        const body = new FormData();
        body.append('image', blob, `${page}.${extension}`);
        body.append('words', JSON.stringify(words));
        const url = item.dataset.store.replace('__PAGE__', page);
        try {
            await post(url, body);
        } catch {
            await post(url, body); // one retry for a dropped connection
        }
    };

    const show = (text, percent = null) => {
        status.textContent = text;
        bar.hidden = percent === null;
        if (percent !== null) fill.style.width = `${percent}%`;
    };

    const run = async () => {
        button.disabled = true;
        window.onbeforeunload = () => true; // warn before leaving mid-way
        try {
            show('Membuka PDF…', 0);
            await post(item.dataset.start);

            const pdf = await getDocument({ url: item.dataset.pdf, withCredentials: true }).promise;
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const uploads = new Set();
            let format = null;
            let size = null;

            for (let n = 1; n <= pdf.numPages; n++) {
                show(`Menukar halaman ${n} / ${pdf.numPages}…`, ((n - 1) / pdf.numPages) * 100);

                const page = await pdf.getPage(n);
                const base = page.getViewport({ scale: 1 });
                const viewport = page.getViewport({ scale: width / base.width });
                canvas.width = Math.round(viewport.width);
                canvas.height = Math.round(viewport.height);
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                await page.render({ canvasContext: ctx, viewport }).promise;
                const words = await pageWords(page).catch(() => []);
                page.cleanup();

                // WebP where the browser can make it (Chrome, Edge, Firefox), JPEG otherwise.
                let blob = format !== 'jpg' ? await toBlob(canvas, 'image/webp', 0.8) : null;
                if (!blob || blob.type !== 'image/webp') blob = await toBlob(canvas, 'image/jpeg', 0.82);
                format ??= blob.type === 'image/webp' ? 'webp' : 'jpg';
                size ??= { width: canvas.width, height: canvas.height };

                const job = upload(n, blob, format, words);
                uploads.add(job);
                job.then(() => uploads.delete(job), () => {});
                if (uploads.size >= MAX_UPLOADS) await Promise.race(uploads);
            }

            show('Menyimpan…', 100);
            await Promise.all(uploads);

            const body = new FormData();
            body.append('pages', pdf.numPages);
            body.append('format', format);
            body.append('width', size.width);
            body.append('height', size.height);
            await post(item.dataset.finish, body);

            show(`✓ Versi pantas sedia: ${pdf.numPages} halaman. Pembaca kini menggunakan gambar ringan.`);
            button.textContent = 'Buat semula';
        } catch (error) {
            console.error(error);
            show('Penukaran gagal. Semak sambungan internet dan klik butang untuk mencuba sekali lagi. Pembaca masih menggunakan PDF penuh.');
        } finally {
            window.onbeforeunload = null;
            button.disabled = false;
        }
    };

    button.addEventListener('click', run);
    return item.dataset.auto === '1' ? run : null;
};

// Automatic runs (right after saving a new PDF) go one file at a time, so a
// big book does not compete with the next one.
const autoRuns = [...document.querySelectorAll('[data-prep-file]')].map(setup).filter(Boolean);
(async () => {
    for (const run of autoRuns) await run();
})();
