// E-book reader (CLAUDE.md: PDF.js in the browser). Pages come from short-lived
// signed URLs, either light page images ("versi pantas", made in the admin) or
// the PDF itself; every drawn page gets the account e-mail as a light watermark.
//
// Two modes: "flip" (default) turns pages like a real book, two pages side by
// side on wide landscape screens and one on phones and upright tablets;
// "single" is the plain one-page view with zoom, for slow devices.
import { GlobalWorkerOptions, getDocument } from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { PageFlip } from 'page-flip/dist/js/page-flip.module.js';

GlobalWorkerOptions.workerSrc = workerUrl;

const SPREAD_MIN_WIDTH = 768; // below this the flip book shows one page

// Two pages side by side only on wide, landscape screens: a tablet held
// upright gets one big page instead of two small ones.
const wantsSpread = (el) => el.clientWidth >= SPREAD_MIN_WIDTH && el.clientWidth > el.clientHeight;
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
    const fullscreenButton = root.querySelector('[data-fullscreen]');
    const fullscreenLabel = root.querySelector('[data-fullscreen-label]');
    const previewCta = root.querySelector('[data-preview-cta]'); // free preview only
    const quizCta = root.querySelector('[data-quiz-cta]'); // Mod Anak, books with a quiz
    const watermark = root.dataset.watermark;
    const pageKey = `asuh.reader.${root.dataset.book}`;
    const modeKey = 'asuh.reader.mode';

    const progressUrl = root.dataset.progressUrl; // Mod Anak only: reading time for the child
    let lastActivity = Date.now();
    let seenPage = 0;
    let progressTimer = null;

    const sendProgress = (tick) => {
        if (!progressUrl || !doc) return;
        fetch(progressUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf },
            credentials: 'same-origin',
            keepalive: true,
            body: JSON.stringify({ page, tick }),
        }).catch(() => { /* offline: the next heartbeat tries again */ });
    };

    // A minute counts only while the book is on screen and pages are being turned.
    const ACTIVE_WINDOW_MS = 3 * 60 * 1000;
    const startHeartbeat = () => {
        if (!progressUrl || progressTimer) return;
        progressTimer = setInterval(() => {
            if (document.visibilityState === 'visible' && Date.now() - lastActivity < ACTIVE_WINDOW_MS) {
                sendProgress(true);
            }
        }, 60 * 1000);
    };

    let doc = null; // page source: light images or the PDF (see the sources below)
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

    // --- Page sources: numPages, size(n), draw(canvas, n, cssWidth, task), prefetch(n)

    const pixelRatio = () => Math.min(window.devicePixelRatio || 1, 2);

    const pdfSource = (pdf) => ({
        numPages: doc.numPages,

        async size(n) {
            const viewport = (await pdf.getPage(n)).getViewport({ scale: 1 });
            return { width: viewport.width, height: viewport.height };
        },

        async draw(target, n, cssWidth, task) {
            const pdfPage = await pdf.getPage(n);
            const base = pdfPage.getViewport({ scale: 1 });
            const scale = cssWidth / base.width;
            const viewport = pdfPage.getViewport({ scale });
            const ratio = pixelRatio();

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
        },

        prefetch(n) {
            pdf.getPage(n).then((p) => p.getOperatorList()).catch(() => {});
        },
    });

    // "Versi pantas": small images the browser decodes natively, much quicker
    // on phones than print-resolution PDF pages.
    const imageSource = ({ width, height, urls }) => {
        const images = new Map(); // page -> Promise<HTMLImageElement>, most recent last

        const fetchImage = (n) => {
            if (images.has(n)) {
                const cached = images.get(n);
                images.delete(n);
                images.set(n, cached);
                return cached;
            }
            const img = new Image();
            img.decoding = 'async';
            img.src = urls[n - 1];
            const loaded = img.decode().then(() => img);
            loaded.catch(() => images.delete(n)); // try again next time
            images.set(n, loaded);
            if (images.size > 12) images.delete(images.keys().next().value);
            return loaded;
        };

        return {
            numPages: urls.length,

            async size() {
                return { width, height };
            },

            async draw(target, n, cssWidth, task) {
                let cancelled = false;
                task?.({ cancel: () => { cancelled = true; } });
                const img = await fetchImage(n);
                if (cancelled) return false;

                const cssHeight = cssWidth * img.naturalHeight / img.naturalWidth;
                const ratio = pixelRatio();
                target.width = Math.floor(cssWidth * ratio);
                target.height = Math.floor(cssHeight * ratio);

                const ctx = target.getContext('2d');
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.drawImage(img, 0, 0, cssWidth, cssHeight);
                drawWatermark(ctx, cssWidth, cssHeight, cssWidth / 600);
                return true;
            },

            prefetch(n) {
                fetchImage(n).catch(() => {});
            },
        };
    };

    const updateNav = (first, last) => {
        pageLabel.textContent = first === last
            ? `Halaman ${first} / ${doc.numPages}`
            : `Halaman ${first}–${last} / ${doc.numPages}`;
        prev.disabled = first <= 1;
        next.disabled = last >= doc.numPages;
        if (quizCta && last >= doc.numPages && quizCta.hidden) {
            quizCta.hidden = false;
            sendProgress(false); // the quiz opens only after the book is recorded as read
            window.dispatchEvent(new Event('resize'));
        }
        if (previewCta) {
            const ended = last >= doc.numPages;
            if (previewCta.hidden === ended) {
                previewCta.hidden = !ended;
                window.dispatchEvent(new Event('resize')); // the bar takes room from the book
            }
            return; // a preview does not move the bookmark of the full book
        }
        store(pageKey, String(first));
        if (first !== seenPage) {
            seenPage = first;
            lastActivity = Date.now();
        }
    };

    // --- Single page mode -----------------------------------------------------

    const single = {
        zoom: 1,
        rendering: null,

        async render() {
            single.rendering?.cancel();
            const base = await doc.size(page);
            const cssWidth = Math.min(stage.clientWidth - 16, 900) * single.zoom;
            canvas.style.width = `${Math.floor(cssWidth)}px`;
            canvas.style.height = `${Math.floor(cssWidth * base.height / base.width)}px`;
            const done = await doc.draw(canvas, page, cssWidth, (task) => { single.rendering = task; });
            if (!done) return;
            updateNav(page, page);
            // Fetch the next page in the background so turning is quick.
            if (page < doc.numPages) doc.prefetch(page + 1);
        },

        go(n) {
            const target = Math.min(Math.max(n, 1), doc.numPages);
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
        pageRatio: 1.414, // height / width, read from the first page
        pageWidth: 0,
        sheets: [], // { canvas, width (drawn CSS width, 0 = empty) }

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
            const last = landscape && index > 0 && index + 1 < doc.numPages ? index + 2 : index + 1;
            return [index + 1, last];
        },

        draw() {
            const [first, last] = flip.visible();

            flip.sheets.forEach((sheet, i) => {
                const number = i + 1;
                if ((number < first - KEEP.before || number > last + KEEP.after) && sheet.width) {
                    sheet.canvas.width = 0;
                    sheet.canvas.height = 0;
                    sheet.width = 0;
                }
            });

            // The pages on screen first, then the next ones, then the ones behind.
            // One page at a time: a page is downloaded when it is drawn, so
            // drawing several at once splits a slow phone connection.
            const ahead = flip.spread ? RENDER_AHEAD.after : 2;
            const behind = flip.spread ? RENDER_AHEAD.before : 1;
            const order = [];
            for (let n = first; n <= last; n++) order.push(n);
            for (let n = last + 1; n <= last + ahead; n++) order.push(n);
            for (let n = first - 1; n >= first - behind; n--) order.push(n);

            flip.queue = order.filter((n) => n >= 1 && n <= doc.numPages);
            flip.pump();
        },

        queue: [],
        busy: false,

        pump() {
            if (flip.busy || !flip.book) return;
            const number = flip.queue.shift();
            if (number === undefined) return;
            const sheet = flip.sheets[number - 1];
            if (sheet.width >= flip.pageWidth * 0.9) { flip.pump(); return; }

            flip.busy = true;
            const width = flip.pageWidth;
            doc.draw(sheet.canvas, number, width)
                .then((done) => { if (done) sheet.width = width; })
                .catch(() => {})
                .finally(() => {
                    flip.busy = false;
                    flip.pump(); // the queue may belong to a rebuilt book by now
                });
        },

        onFlip() {
            const [first, last] = flip.visible();
            page = first;
            updateNav(first, last);
            flip.draw();
        },

        async build() {
            const base = await doc.size(1);
            flip.pageRatio = base.height / base.width;
            flip.spread = wantsSpread(stage);

            flip.sizer = document.createElement('div');
            flip.sizer.className = 'mx-auto';
            const bookEl = document.createElement('div');
            flip.sizer.append(bookEl);
            flipHost.append(flip.sizer);
            flip.fit();

            flip.sheets = [];
            const pages = [];
            for (let i = 0; i < doc.numPages; i++) {
                const el = document.createElement('div');
                el.className = 'bg-white';
                if (i === 0 || i === doc.numPages - 1) el.dataset.density = 'hard';
                const sheetCanvas = document.createElement('canvas');
                el.append(sheetCanvas);
                pages.push(el);
                flip.sheets.push({ canvas: sheetCanvas, width: 0 });
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
            if (wantsSpread(stage) !== flip.spread) {
                flip.stop();
                flip.start();
                return;
            }
            flip.fit();
            flip.book.update();
            flip.draw();
        },

        go(n) {
            const target = Math.min(Math.max(n, 1), doc.numPages);
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
            flip.queue = [];
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
    modeToggle.addEventListener('click', () => { if (doc) setMode(mode === 'flip' ? 'single' : 'flip'); });

    // Full screen: on tablets the browser's own bars take a quarter of the
    // height, and the book is fitted to the height. Not offered where the
    // browser does not allow it (iPhone).
    const fullscreenElement = () => document.fullscreenElement || document.webkitFullscreenElement;
    if (fullscreenButton && (document.fullscreenEnabled || document.webkitFullscreenEnabled)) {
        fullscreenButton.hidden = false;
        fullscreenButton.addEventListener('click', () => {
            if (fullscreenElement()) {
                (document.exitFullscreen || document.webkitExitFullscreen).call(document);
            } else {
                const el = document.documentElement;
                const request = el.requestFullscreen || el.webkitRequestFullscreen;
                Promise.resolve(request.call(el, { navigationUI: 'hide' })).catch(() => {});
            }
        });
        const onFullscreenChange = () => {
            const on = Boolean(fullscreenElement());
            fullscreenButton.setAttribute('aria-label', on ? 'Keluar skrin penuh' : 'Skrin penuh');
            if (fullscreenLabel) fullscreenLabel.textContent = on ? 'Keluar' : 'Skrin penuh';
            window.dispatchEvent(new Event('resize')); // refit the book to the new height
        };
        document.addEventListener('fullscreenchange', onFullscreenChange);
        document.addEventListener('webkitfullscreenchange', onFullscreenChange);
    }

    document.addEventListener('keydown', (event) => {
        if (!doc) return;
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
            if (!doc) return;
            mode === 'flip' ? flip.resize() : single.render();
        }, 200);
    });

    const open = (source) => {
        doc = source;
        const saved = root.dataset.startPage || load(pageKey);
        page = previewCta ? 1 : Math.min(parseInt(saved, 10) || 1, doc.numPages);
        status.remove();
        modeToggle.hidden = false;
        canvas.hidden = true;
        flipHost.hidden = true;
        setMode(load(modeKey) === 'single' ? 'single' : 'flip');
        sendProgress(false);
        startHeartbeat();
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') sendProgress(false); // keep the page reached
    });

    if (root.dataset.pages) {
        open(imageSource(JSON.parse(root.dataset.pages)));
    } else {
        // The server answers Range requests, so PDF.js fetches only the pieces
        // it needs (2 MB at a time) instead of waiting for the whole book.
        getDocument({
            url: root.dataset.src,
            withCredentials: true,
            disableAutoFetch: true,
            disableStream: true,
            rangeChunkSize: 2 * 1024 * 1024,
        })
            .promise.then((pdf) => open(pdfSource(pdf)))
            .catch(() => {
                status.textContent = 'Buku tidak dapat dibuka. Muat semula halaman ini untuk mencuba sekali lagi.';
                status.classList.add('text-magenta');
            });
    }
}
