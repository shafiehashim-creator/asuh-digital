// Animated video player for Mod Anak (Phase 2), also used for the ASUH
// music video on public pages (started from its poster, see brand-video). YouTube plays through its
// IFrame API with ASUH's own buttons; a cover over the frame stops taps from
// reaching YouTube's title, logo or suggestions, and ASUH's own screens show
// when the video is paused or ends. Bunny Stream uses its own clean player.
// Time watched goes to the server every 30 seconds for the daily limit.

// The YouTube IFrame API calls one global function once loaded, so every
// player on the page waits on the same promise.
let youTubeApi = null;
const loadYouTubeApi = () => {
    youTubeApi ??= new Promise((resolve) => {
        window.onYouTubeIframeAPIReady = () => resolve(window.YT);
        const script = document.createElement('script');
        script.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(script);
    });
    return youTubeApi;
};

function initVideoPlayer(root) {
    if (root.dataset.started) return;
    root.dataset.started = '1';

    // Controls sit next to the frame, inside the same wrapper.
    const wrap = root.closest('[data-video-wrap]') ?? document;
    const { provider, videoId, embedUrl, progressUrl, csrf } = root.dataset;
    const autoplay = root.dataset.autoplay === '1';
    let secondsLeft = root.dataset.secondsLeft === '' ? null : parseInt(root.dataset.secondsLeft, 10);
    const startAt = parseInt(root.dataset.start || '0', 10);

    const frameHost = root.querySelector('[data-frame]');
    const cover = root.querySelector('[data-cover]');
    const pausedScreen = root.querySelector('[data-paused]');
    const endScreen = root.querySelector('[data-ended]');
    const limitScreen = root.querySelector('[data-limit]');
    const playButton = wrap.querySelector('[data-play]');
    const seek = wrap.querySelector('[data-seek]');
    const timeLabel = wrap.querySelector('[data-time]');
    const fullscreenButton = wrap.querySelector('[data-fullscreen]');
    const zoomButton = wrap.querySelector('[data-zoom]');
    const replayButton = root.querySelector('[data-replay]');

    let player = null; // { play, pause, seekTo, time, duration }
    let playing = false;
    let unsent = 0; // seconds played since the last heartbeat
    let lastTick = null;

    const format = (s) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;

    const show = (el, visible) => { if (el) el.hidden = !visible; };

    const send = (ended = false) => {
        const seconds = Math.round(unsent);
        unsent = 0;
        if (!player || !progressUrl || (seconds === 0 && !ended)) return; // staff preview: nothing recorded
        fetch(progressUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin',
            keepalive: true,
            body: JSON.stringify({ seconds, position: Math.floor(player.time()), ended }),
        })
            .then((r) => (r.ok ? r.json() : null))
            .then((data) => {
                if (data && data.seconds_left !== null && data.seconds_left !== undefined) {
                    secondsLeft = data.seconds_left;
                    if (secondsLeft <= 0) stopForLimit();
                }
            })
            .catch(() => { /* offline: counted with the next heartbeat */ });
    };

    const stopForLimit = () => {
        player?.pause();
        show(limitScreen, true);
        show(pausedScreen, false);
    };

    // Count seconds actually played, every second, and report every 30.
    setInterval(() => {
        const now = Date.now();
        if (playing && lastTick) {
            const delta = (now - lastTick) / 1000;
            unsent += delta;
            if (secondsLeft !== null) {
                secondsLeft -= delta;
                if (secondsLeft <= 0) { send(); stopForLimit(); }
            }
        }
        lastTick = now;
        if (player) {
            const t = player.time();
            const d = player.duration();
            if (seek && d) { seek.max = String(Math.floor(d)); if (!seek.matches(':active')) seek.value = String(Math.floor(t)); }
            if (timeLabel) timeLabel.textContent = d ? `${format(t)} / ${format(d)}` : format(t);
        }
        if (unsent >= 30) send();
    }, 1000);

    // Big screen (filled or full screen): the controls float over the video
    // and fade out after a few seconds of playing, as in the YouTube app.
    const fullTarget = wrap === document ? root : wrap;
    let hideTimer = null;
    const isBig = () => fullTarget.classList.contains('video-big');
    const controlsHidden = () => fullTarget.classList.contains('controls-hidden');
    const showControls = () => {
        fullTarget.classList.remove('controls-hidden');
        clearTimeout(hideTimer);
        if (isBig() && playing) hideTimer = setTimeout(() => fullTarget.classList.add('controls-hidden'), 3000);
    };

    const setPlaying = (value) => {
        playing = value;
        lastTick = Date.now();
        if (playButton) {
            playButton.textContent = value ? '❚❚' : '▶';
            playButton.setAttribute('aria-label', value ? root.dataset.labelPause || 'Jeda' : root.dataset.labelPlay || 'Main');
        }
        showControls();
    };

    const onEnded = () => {
        setPlaying(false);
        send(true);
        show(pausedScreen, false);
        show(endScreen, true);
    };

    const toggle = () => {
        if (!player || (secondsLeft !== null && secondsLeft <= 0)) return;
        playing ? player.pause() : player.play();
    };

    playButton?.addEventListener('click', toggle);
    // On a big screen a tap first brings the hidden controls back.
    cover?.addEventListener('click', () => (isBig() && controlsHidden() ? showControls() : toggle()));
    wrap.querySelector('[data-controls]')?.addEventListener('pointerdown', showControls);
    pausedScreen?.addEventListener('click', toggle);
    seek?.addEventListener('change', () => player?.seekTo(parseInt(seek.value, 10)));
    replayButton?.addEventListener('click', () => { show(endScreen, false); player?.seekTo(0); player?.play(); });
    // Full screen: the browser's own where allowed. iPhone (Safari and the
    // installed app) has no full screen for page elements, so there the
    // player fills the screen itself instead (.video-filled in app.css).
    let filled = false;
    const setBig = (on) => {
        fullTarget.classList.toggle('video-big', on);
        if (!on) setZoom(false);
        if (fullscreenButton) fullscreenButton.setAttribute('aria-label', on ? root.dataset.labelExitFull || 'Keluar skrin penuh' : root.dataset.labelFull || 'Skrin penuh');
        showControls();
    };
    // "Penuhkan": cover the whole screen, cutting a little off the top and
    // bottom, instead of black bars at the sides.
    const setZoom = (on) => {
        fullTarget.classList.toggle('video-zoomed', on);
        zoomButton?.setAttribute('aria-pressed', on ? 'true' : 'false');
    };
    zoomButton?.addEventListener('click', () => setZoom(!fullTarget.classList.contains('video-zoomed')));
    const fill = (on) => {
        filled = on;
        fullTarget.classList.toggle('video-filled', on);
        document.documentElement.classList.toggle('overflow-hidden', on);
        setBig(on);
    };
    const onFullscreenChange = () => setBig((document.fullscreenElement || document.webkitFullscreenElement) === fullTarget);
    document.addEventListener('fullscreenchange', onFullscreenChange);
    document.addEventListener('webkitfullscreenchange', onFullscreenChange);
    fullscreenButton?.addEventListener('click', async () => {
        if (filled) {
            fill(false);

            return;
        }
        if (document.fullscreenElement || document.webkitFullscreenElement) {
            await (document.exitFullscreen ?? document.webkitExitFullscreen)?.call(document)?.catch?.(() => {});

            return;
        }
        const request = fullTarget.requestFullscreen ?? fullTarget.webkitRequestFullscreen;
        if (request && (document.fullscreenEnabled || document.webkitFullscreenEnabled)) {
            try {
                await request.call(fullTarget);
                // Phones: turn sideways for the 16:9 picture where the browser allows it.
                await screen.orientation?.lock?.('landscape').catch(() => {});

                return;
            } catch { /* not allowed here: fill the screen instead */ }
        }
        fill(true);
    });
    document.addEventListener('keydown', (event) => { if (filled && event.key === 'Escape') fill(false); });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') { player?.pause(); send(); }
    });
    window.addEventListener('pagehide', () => send());

    if (provider === 'youtube') {
        const holder = document.createElement('div');
        frameHost.appendChild(holder);
        loadYouTubeApi().then((YT) => {
            const yt = new YT.Player(holder, {
                host: 'https://www.youtube-nocookie.com',
                videoId,
                width: '100%',
                height: '100%',
                playerVars: {
                    controls: 0, rel: 0, modestbranding: 1, playsinline: 1, disablekb: 1,
                    fs: 0, iv_load_policy: 3, cc_load_policy: 0, start: startAt,
                },
                events: {
                    onReady: () => {
                        player = {
                            play: () => yt.playVideo(),
                            pause: () => yt.pauseVideo(),
                            seekTo: (s) => yt.seekTo(s, true),
                            time: () => yt.getCurrentTime() || 0,
                            duration: () => yt.getDuration() || 0,
                        };
                        root.querySelector('[data-loading]')?.remove();
                        // Keep YouTube's picture matched to its frame whenever the frame
                        // changes size (filling the screen, turning the phone). iPhone
                        // otherwise leaves it shifted with a black bar.
                        if ('ResizeObserver' in window) {
                            new ResizeObserver(() => {
                                if (frameHost.clientWidth && frameHost.clientHeight) yt.setSize(frameHost.clientWidth, frameHost.clientHeight);
                            }).observe(frameHost);
                        }
                        if (secondsLeft !== null && secondsLeft <= 0) stopForLimit();
                        else show(pausedScreen, true);
                        // Started from a tap (the music video's poster): try to play straight
                        // away; where the browser refuses, "Tekan untuk menonton" stays.
                        if (autoplay) player.play();
                    },
                    onStateChange: (e) => {
                        const S = YT.PlayerState;
                        if (e.data === S.PLAYING) { setPlaying(true); show(pausedScreen, false); show(endScreen, false); }
                        if (e.data === S.PAUSED) { setPlaying(false); show(pausedScreen, true); send(); }
                        if (e.data === S.ENDED) onEnded();
                    },
                },
            });
        });
    } else {
        // Bunny Stream: its own controls; player.js events drive the count.
        const iframe = document.createElement('iframe');
        iframe.src = embedUrl;
        iframe.className = 'h-full w-full';
        iframe.allow = 'accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture; fullscreen';
        iframe.allowFullscreen = true;
        frameHost.appendChild(iframe);
        cover?.remove();
        wrap.querySelector('[data-controls]')?.remove();
        root.querySelector('[data-loading]')?.remove();
        const script = document.createElement('script');
        script.src = 'https://assets.mediadelivery.net/playerjs/player-0.1.0.min.js';
        script.onload = () => {
            const p = new window.playerjs.Player(iframe);
            let time = startAt;
            let duration = 0;
            p.on('ready', () => {
                player = {
                    play: () => p.play(), pause: () => p.pause(), seekTo: (s) => p.setCurrentTime(s),
                    time: () => time, duration: () => duration,
                };
                if (startAt) p.setCurrentTime(startAt);
                p.on('play', () => setPlaying(true));
                p.on('pause', () => { setPlaying(false); send(); });
                p.on('ended', onEnded);
                p.on('timeupdate', (d) => { time = d.seconds; duration = d.duration; });
                if (secondsLeft !== null && secondsLeft <= 0) stopForLimit();
            });
        };
        document.head.appendChild(script);
    }
}

window.initVideoPlayer = initVideoPlayer;
document.querySelectorAll('[data-video-player]:not([data-lazy])').forEach(initVideoPlayer);
