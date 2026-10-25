// "Versi pantas" (admin): renders every PDF page to a light image in this
// browser and uploads it, so readers on phones get small images instead of
// print-resolution PDF pages. See App\Http\Controllers\Admin\PageImageController.
import { GlobalWorkerOptions, getDocument } from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

GlobalWorkerOptions.workerSrc = workerUrl;

const MAX_UPLOADS = 3; // uploads in flight while the next pages render

const toBlob = (canvas, type, quality) => new Promise((resolve) => canvas.toBlob(resolve, type, quality));

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

    const upload = async (page, blob, extension) => {
        const body = new FormData();
        body.append('image', blob, `${page}.${extension}`);
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
                page.cleanup();

                // WebP where the browser can make it (Chrome, Edge, Firefox), JPEG otherwise.
                let blob = format !== 'jpg' ? await toBlob(canvas, 'image/webp', 0.8) : null;
                if (!blob || blob.type !== 'image/webp') blob = await toBlob(canvas, 'image/jpeg', 0.82);
                format ??= blob.type === 'image/webp' ? 'webp' : 'jpg';
                size ??= { width: canvas.width, height: canvas.height };

                const job = upload(n, blob, format);
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
