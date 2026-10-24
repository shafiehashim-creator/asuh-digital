// E-book reader (CLAUDE.md: PDF.js in the browser). The PDF comes from a
// short-lived signed URL; every rendered page gets the account e-mail drawn
// into the canvas as a light watermark.
import { GlobalWorkerOptions, getDocument } from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

GlobalWorkerOptions.workerSrc = workerUrl;

const root = document.getElementById('reader');

if (root) {
    const canvas = root.querySelector('[data-canvas]');
    const stage = root.querySelector('[data-stage]');
    const status = root.querySelector('[data-status]');
    const pageLabel = root.querySelector('[data-page]');
    const prev = root.querySelector('[data-prev]');
    const next = root.querySelector('[data-next]');
    const zoomIn = root.querySelector('[data-zoom-in]');
    const zoomOut = root.querySelector('[data-zoom-out]');
    const watermark = root.dataset.watermark;
    const storageKey = `asuh.reader.${root.dataset.book}`;

    let pdf = null;
    let page = 1;
    let zoom = 1;
    let rendering = null;

    // Per-viewer convenience only; the reader works without it.
    const savedPage = () => {
        try { return parseInt(localStorage.getItem(storageKey), 10) || 1; } catch { return 1; }
    };
    const savePage = (n) => {
        try { localStorage.setItem(storageKey, String(n)); } catch { /* storage unavailable */ }
    };

    const drawWatermark = (ctx, width, height, scale) => {
        ctx.save();
        ctx.globalAlpha = 0.12;
        ctx.fillStyle = '#231F20';
        ctx.font = `${Math.round(14 * scale)}px Nunito, sans-serif`;
        ctx.translate(width / 2, height / 2);
        ctx.rotate(-Math.PI / 6);
        const step = 140 * scale;
        for (let y = -height; y < height; y += step) {
            for (let x = -width; x < width; x += 320 * scale) {
                ctx.fillText(watermark, x, y);
            }
        }
        ctx.restore();
    };

    const render = async () => {
        if (!pdf) return;
        rendering?.cancel();

        const pdfPage = await pdf.getPage(page);
        const available = Math.min(stage.clientWidth, 900);
        const base = pdfPage.getViewport({ scale: 1 });
        const scale = (available / base.width) * zoom;
        const viewport = pdfPage.getViewport({ scale });
        const ratio = window.devicePixelRatio || 1;

        canvas.width = Math.floor(viewport.width * ratio);
        canvas.height = Math.floor(viewport.height * ratio);
        canvas.style.width = `${Math.floor(viewport.width)}px`;
        canvas.style.height = `${Math.floor(viewport.height)}px`;

        const ctx = canvas.getContext('2d');
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

        rendering = pdfPage.render({ canvasContext: ctx, viewport });
        try {
            await rendering.promise;
        } catch (error) {
            if (error?.name === 'RenderingCancelledException') return;
            throw error;
        }
        drawWatermark(ctx, viewport.width, viewport.height, scale);

        pageLabel.textContent = `Halaman ${page} / ${pdf.numPages}`;
        prev.disabled = page <= 1;
        next.disabled = page >= pdf.numPages;
        savePage(page);
    };

    const go = (n) => {
        if (!pdf) return;
        const target = Math.min(Math.max(n, 1), pdf.numPages);
        if (target === page) return;
        page = target;
        render();
        stage.scrollTo({ top: 0 });
    };

    prev.addEventListener('click', () => go(page - 1));
    next.addEventListener('click', () => go(page + 1));
    zoomIn.addEventListener('click', () => { zoom = Math.min(zoom + 0.25, 3); render(); });
    zoomOut.addEventListener('click', () => { zoom = Math.max(zoom - 0.25, 0.5); render(); });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight') go(page + 1);
        if (event.key === 'ArrowLeft') go(page - 1);
    });

    let touchStart = null;
    stage.addEventListener('touchstart', (e) => { touchStart = e.changedTouches[0].clientX; }, { passive: true });
    stage.addEventListener('touchend', (e) => {
        if (touchStart === null || zoom > 1) return;
        const dx = e.changedTouches[0].clientX - touchStart;
        if (Math.abs(dx) > 60) go(dx < 0 ? page + 1 : page - 1);
        touchStart = null;
    });

    // Light deterrent only; the real protection is server-side.
    canvas.addEventListener('contextmenu', (e) => e.preventDefault());

    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(render, 150);
    });

    getDocument({ url: root.dataset.src, withCredentials: true, disableAutoFetch: true })
        .promise.then((doc) => {
            pdf = doc;
            page = Math.min(savedPage(), doc.numPages);
            status.remove();
            return render();
        })
        .catch(() => {
            status.textContent = 'Buku tidak dapat dibuka. Muat semula halaman ini untuk mencuba sekali lagi.';
            status.classList.add('text-magenta');
        });
}
