// Countdown to a campaign's end (x-campaign-bar, x-campaign-banner). The
// server sends the seconds left, so a phone with the wrong clock still
// counts right; the countdown hides itself when it reaches zero. A
// "coming soon" countdown reloads the page instead, to show the campaign
// price once it starts.
export function countdown(seconds, reloadAtZero = false) {
    return {
        end: Date.now() + seconds * 1000,
        left: seconds,
        ended: seconds <= 0,
        copied: false,
        init() {
            const tick = () => {
                this.left = Math.max(0, Math.round((this.end - Date.now()) / 1000));
                if (this.left === 0) {
                    clearInterval(timer);
                    if (reloadAtZero) {
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        this.ended = true;
                    }
                }
            };
            const timer = setInterval(tick, 1000);
        },
        get days() {
            return Math.floor(this.left / 86400);
        },
        get hours() {
            return String(Math.floor((this.left % 86400) / 3600)).padStart(2, '0');
        },
        get minutes() {
            return String(Math.floor((this.left % 3600) / 60)).padStart(2, '0');
        },
        get seconds() {
            return String(this.left % 60).padStart(2, '0');
        },
        copy(code) {
            navigator.clipboard?.writeText(code).then(() => {
                this.copied = true;
                setTimeout(() => (this.copied = false), 2000);
            });
        },
    };
}
