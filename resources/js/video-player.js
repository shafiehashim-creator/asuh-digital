// Animated video player for Mod Anak (Phase 2). YouTube plays through its
// IFrame API with ASUH's own buttons; a cover over the frame stops taps from
// reaching YouTube's title, logo or suggestions, and ASUH's own screens show
// when the video is paused or ends. Bunny Stream uses its own clean player.
// Time watched goes to the server every 30 seconds for the daily limit.

const root = document.getElementById('video-player');

if (root) {
    const { provider, videoId, embedUrl, progressUrl, csrf } = root.dataset;
    let secondsLeft = root.dataset.secondsLeft === '' ? null : parseInt(root.dataset.secondsLeft, 10);
    const startAt = parseInt(root.dataset.start || '0', 10);

    const frameHost = root.querySelector('[data-frame]');
    const cover = root.querySelector('[data-cover]');
    const pausedScreen = root.querySelector('[data-paused]');
    const endScreen = root.querySelector('[data-ended]');
    const limitScreen = root.querySelector('[data-limit]');
    const playButton = document.querySelector('[data-play]');
    const seek = document.querySelector('[data-seek]');
    const timeLabel = document.querySelector('[data-time]');
    const fullscreenButton = document.querySelector('[data-fullscreen]');
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

    const setPlaying = (value) => {
        playing = value;
        lastTick = Date.now();
        if (playButton) {
            playButton.textContent = value ? '❚❚' : '▶';
            playButton.setAttribute('aria-label', value ? 'Jeda' : 'Main');
        }
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
    cover?.addEventListener('click', toggle);
    pausedScreen?.addEventListener('click', toggle);
    seek?.addEventListener('change', () => player?.seekTo(parseInt(seek.value, 10)));
    replayButton?.addEventListener('click', () => { show(endScreen, false); player?.seekTo(0); player?.play(); });
    fullscreenButton?.addEventListener('click', async () => {
        try {
            if (document.fullscreenElement) {
                await document.exitFullscreen();
            } else {
                await (document.getElementById('video-wrap') ?? root).requestFullscreen();
                // Phones: turn sideways for the 16:9 picture where the browser allows it.
                await screen.orientation?.lock?.('landscape').catch(() => {});
            }
        } catch { /* fullscreen not allowed */ }
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') { player?.pause(); send(); }
    });
    window.addEventListener('pagehide', () => send());

    if (provider === 'youtube') {
        const holder = document.createElement('div');
        frameHost.appendChild(holder);
        window.onYouTubeIframeAPIReady = () => {
            const yt = new window.YT.Player(holder, {
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
                        if (secondsLeft !== null && secondsLeft <= 0) stopForLimit();
                        else show(pausedScreen, true);
                    },
                    onStateChange: (e) => {
                        const S = window.YT.PlayerState;
                        if (e.data === S.PLAYING) { setPlaying(true); show(pausedScreen, false); show(endScreen, false); }
                        if (e.data === S.PAUSED) { setPlaying(false); show(pausedScreen, true); send(); }
                        if (e.data === S.ENDED) onEnded();
                    },
                },
            });
        };
        const script = document.createElement('script');
        script.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(script);
    } else {
        // Bunny Stream: its own controls; player.js events drive the count.
        const iframe = document.createElement('iframe');
        iframe.src = embedUrl;
        iframe.className = 'h-full w-full';
        iframe.allow = 'accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture; fullscreen';
        iframe.allowFullscreen = true;
        frameHost.appendChild(iframe);
        cover?.remove();
        document.querySelector('[data-controls]')?.remove();
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
