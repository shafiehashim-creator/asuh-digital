// E-book reader (CLAUDE.md: PDF.js in the browser). The PDF comes from a
// short-lived signed URL; every rendered page gets the account e-mail drawn
// into the canvas as a light watermark.
//
// Two modes: "flip" (default) turns pages like a real book, two pages side by
// side on large screens and one on phones; "single" is the plain one-page view
// with zoom, for slow devices.
import { GlobalWorkerOptions, getDocument } from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { PageFlip } from 'page-flip/dist/js/page-flip.module.js';

GlobalWorkerOptions.workerSrc = workerUrl;

const SPREAD_MIN_WIDTH = 768; // below this the flip book shows one page
const RENDER_AHEAD = { before: 2, after: 3 }; // pages kept drawn around the current one
const KEEP = { before: 4, after: 5 }; // pages further away are released to save memory

const root = document.getElementById('reader');

if (root) {
    const stage = root.querySelector('[data-stage]');
    const status = root.querySelector('[data-status]');
    const canvas = root.querySelector('[data-canvas]');
    const flipHost = root.querySelector('[data-flip]');
    const pageLabel = root.querySelector('[data-page]');
    const prev = root.querySelector('[data-prev]');
    const next = root.querySelector('[data-next]');
    const zoomIn = root.querySelector('[data-zoom-in]');
    const zoomOut = root.querySelector('[data-zoom-out]');
    const modeToggle = root.querySelector('[data-mode-toggle]');
    const watermark = root.dataset.watermark;
    const pageKey = `asuh.reader.${root.dataset.book}`;
    const modeKey = 'asuh.reader.mode';

    let pdf = null;
    let page = 1; // 1-based, shared by both modes
    let mode = 'flip';

    // Per-viewer conveniences only; the reader works without them.
    const load = (key) => { try { return localStorage.getItem(key); } catch { return null; } };
    const store = (key, value) => { try { localStorage.setItem(key, value); } catch { /* storage unavailable */ } };

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

    // Draws one PDF page into a canvas at the given CSS width.
    const paint = async (target, number, cssWidth, task) => {
        const pdfPage = await pdf.getPage(number);
        const base = pdfPage.getViewport({ scale: 1 });
        const scale = cssWidth / base.width;
        const viewport = pdfPage.getViewport({ scale });
        const ratio = Math.min(window.devicePixelRatio || 1, 2);

        target.width = Math.floor(viewport.width * ratio);
        target.height = Math.floor(viewport.height * ratio);

        const ctx = target.getContext('2d');
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

        const rendering = pdfPage.render({ canvasContext: ctx, viewport });
        task?.(rendering);
        try {
            await rendering.promise;
        } catch (error) {
            if (error?.name === 'RenderingCancelledException') return false;
            throw error;
        }
        drawWatermark(ctx, viewport.width, viewport.height, scale);
        return true;
    };

    const updateNav = (first, last) => {
        pageLabel.textContent = first === last
            ? `Halaman ${first} / ${pdf.numPages}`
            : `Halaman ${first}–${last} / ${pdf.numPages}`;
        prev.disabled = first <= 1;
        next.disabled = last >= pdf.numPages;
        store(pageKey, String(first));
    };

    // --- Single page mode -----------------------------------------------------

    const single = {
        zoom: 1,
        rendering: null,

        async render() {
            single.rendering?.cancel();
            const pdfPage = await pdf.getPage(page);
            const base = pdfPage.getViewport({ scale: 1 });
            const cssWidth = Math.min(stage.clientWidth - 16, 900) * single.zoom;
            canvas.style.width = `${Math.floor(cssWidth)}px`;
            canvas.style.height = `${Math.floor(cssWidth * base.height / base.width)}px`;
            const done = await paint(canvas, page, cssWidth, (task) => { single.rendering = task; });
            if (done) updateNav(page, page);
        },

        go(n) {
            const target = Math.min(Math.max(n, 1), pdf.numPages);
            if (target === page) return;
            page = target;
            single.render();
            stage.scrollTo({ top: 0 });
        },

        start() {
            canvas.hidden = false;
            zoomIn.hidden = false;
            zoomOut.hidden = false;
            single.render();
        },

        stop() {
            single.rendering?.cancel();
            canvas.hidden = true;
            zoomIn.hidden = true;
            zoomOut.hidden = true;
        },
    };

    // --- Flip book mode -------------------------------------------------------

    const flip = {
        book: null,
        sizer: null,
        spread: false,
        pageRatio: 1.414, // height / width, read from the first PDF page
        pageWidth: 0,
        sheets: [], // { canvas, width (drawn CSS width, 0 = empty), pending }

        // Largest book that fits the stage, keeping the PDF page shape.
        fit() {
            const width = stage.clientWidth - 16;
            const height = stage.clientHeight - 32; // stage has py-4
            const pages = flip.spread ? 2 : 1;
            const bookWidth = Math.min(width, (height / flip.pageRatio) * pages);
            flip.sizer.style.width = `${Math.floor(bookWidth)}px`;
            flip.pageWidth = bookWidth / pages;
        },

        visible() {
            const index = flip.book.getCurrentPageIndex();
            const landscape = flip.book.getOrientation() === 'landscape';
            // With a cover, spreads are [1], [2,3], [4,5]...; the index is the left page.
            const last = landscape && index > 0 && index + 1 < pdf.numPages ? index + 2 : index + 1;
            return [index + 1, last];
        },

        draw() {
            const [first] = flip.visible();
            const from = Math.max(first - RENDER_AHEAD.before, 1);
            const to = Math.min(first + 1 + RENDER_AHEAD.after, pdf.numPages);

            flip.sheets.forEach((sheet, i) => {
                const number = i + 1;
                if (number < first - KEEP.before || number > first + 1 + KEEP.after) {
                    if (sheet.width) { sheet.canvas.width = 0; sheet.canvas.height = 0; sheet.width = 0; }
                    return;
                }
                const sharp = sheet.width >= flip.pageWidth * 0.9;
                if (number < from || number > to || sharp || sheet.pending) return;
                sheet.pending = true;
                const width = flip.pageWidth;
                paint(sheet.canvas, number, width)
                    .then((done) => { if (done) sheet.width = width; })
                    .catch(() => {})
                    .finally(() => { sheet.pending = false; });
            });
        },

        onFlip() {
            const [first, last] = flip.visible();
            page = first;
            updateNav(first, last);
            flip.draw();
        },

        async build() {
            const firstPage = await pdf.getPage(1);
            const base = firstPage.getViewport({ scale: 1 });
            flip.pageRatio = base.height / base.width;
            flip.spread = stage.clientWidth >= SPREAD_MIN_WIDTH;

            flip.sizer = document.createElement('div');
            flip.sizer.className = 'mx-auto';
            const bookEl = document.createElement('div');
            flip.sizer.append(bookEl);
            flipHost.append(flip.sizer);
            flip.fit();

            flip.sheets = [];
            const pages = [];
            for (let i = 0; i < pdf.numPages; i++) {
                const el = document.createElement('div');
                el.className = 'bg-white';
                if (i === 0 || i === pdf.numPages - 1) el.dataset.density = 'hard';
                const sheetCanvas = document.createElement('canvas');
                el.append(sheetCanvas);
                pages.push(el);
                flip.sheets.push({ canvas: sheetCanvas, width: 0, pending: false });
            }

            flip.book = new PageFlip(bookEl, {
                width: Math.round(base.width),
                height: Math.round(base.height),
                size: 'stretch',
                // The library picks one or two pages from minWidth; we decide by screen width instead.
                minWidth: flip.spread ? 50 : 100000,
                maxWidth: 4000,
                minHeight: 50,
                maxHeight: 6000,
                usePortrait: !flip.spread,
                showCover: true,
                startPage: page - 1,
                maxShadowOpacity: 0.4,
                flippingTime: 700,
                mobileScrollSupport: false,
            });
            flip.book.loadFromHTML(pages);
            // The library sets a min-width from minWidth; the sizer handles size instead.
            bookEl.style.minWidth = '0';
            bookEl.style.minHeight = '0';

            flip.book.on('flip', flip.onFlip);
            flip.onFlip();
        },

        resize() {
            if ((stage.clientWidth >= SPREAD_MIN_WIDTH) !== flip.spread) {
                flip.stop();
                flip.start();
                return;
            }
            flip.fit();
            flip.book.update();
            flip.draw();
        },

        go(n) {
            const target = Math.min(Math.max(n, 1), pdf.numPages);
            flip.book.turnToPage(target - 1);
            flip.onFlip();
        },

        start() {
            flipHost.hidden = false;
            zoomIn.hidden = true;
            zoomOut.hidden = true;
            stage.classList.add('overflow-hidden');
            return flip.build();
        },

        stop() {
            flip.book?.destroy();
            flip.book = null;
            flip.sizer?.remove();
            flip.sizer = null;
            flip.sheets = [];
            flipHost.hidden = true;
            stage.classList.remove('overflow-hidden');
        },
    };

    // --- Controls -------------------------------------------------------------

    const setMode = (value) => {
        (mode === 'flip' ? flip : single).stop();
        mode = value;
        store(modeKey, mode);
        modeToggle.textContent = mode === 'flip' ? 'Mod biasa' : 'Mod flip';
        modeToggle.setAttribute('aria-label', mode === 'flip' ? 'Tukar ke mod biasa (satu halaman)' : 'Tukar ke mod flip (selak macam buku)');
        (mode === 'flip' ? flip : single).start();
    };

    prev.addEventListener('click', () => (mode === 'flip' ? flip.book?.flipPrev() : single.go(page - 1)));
    next.addEventListener('click', () => (mode === 'flip' ? flip.book?.flipNext() : single.go(page + 1)));
    zoomIn.addEventListener('click', () => { single.zoom = Math.min(single.zoom + 0.25, 3); single.render(); });
    zoomOut.addEventListener('click', () => { single.zoom = Math.max(single.zoom - 0.25, 0.5); single.render(); });
    modeToggle.addEventListener('click', () => { if (pdf) setMode(mode === 'flip' ? 'single' : 'flip'); });

    document.addEventListener('keydown', (event) => {
        if (!pdf) return;
        if (event.key === 'ArrowRight') next.click();
        if (event.key === 'ArrowLeft') prev.click();
    });

    // Swipe for single mode; the flip book handles its own drags and swipes.
    let touchStart = null;
    stage.addEventListener('touchstart', (e) => { touchStart = e.changedTouches[0].clientX; }, { passive: true });
    stage.addEventListener('touchend', (e) => {
        if (mode !== 'single' || touchStart === null || single.zoom > 1) return;
        const dx = e.changedTouches[0].clientX - touchStart;
        if (Math.abs(dx) > 60) single.go(dx < 0 ? page + 1 : page - 1);
        touchStart = null;
    });

    // Light deterrent only; the real protection is server-side.
    stage.addEventListener('contextmenu', (e) => e.preventDefault());

    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (!pdf) return;
            mode === 'flip' ? flip.resize() : single.render();
        }, 200);
    });

    getDocument({ url: root.dataset.src, withCredentials: true, disableAutoFetch: true })
        .promise.then((doc) => {
            pdf = doc;
            page = Math.min(parseInt(load(pageKey), 10) || 1, doc.numPages);
            status.remove();
            modeToggle.hidden = false;
            canvas.hidden = true;
            flipHost.hidden = true;
            setMode(load(modeKey) === 'single' ? 'single' : 'flip');
        })
        .catch(() => {
            status.textContent = 'Buku tidak dapat dibuka. Muat semula halaman ini untuk mencuba sekali lagi.';
            status.classList.add('text-magenta');
        });
}
