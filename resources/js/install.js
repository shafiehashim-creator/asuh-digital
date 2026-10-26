// Installable app (PWA): register the service worker, and an Alpine
// component for the "Pasang app" buttons. Chrome, Edge and Android offer a
// real install prompt; iPhone and iPad only allow Share > Add to Home
// Screen, so they get short instructions instead.

if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

let deferredPrompt = null;
const listeners = new Set();
const notify = () => listeners.forEach((listener) => listener());

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;
    notify();
});

window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    notify();
});

const DISMISS_KEY = 'asuh_install_dismissed';
const DISMISS_DAYS = 30;

const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

const dismissedRecently = () => {
    try {
        const at = Number(localStorage.getItem(DISMISS_KEY) || 0);

        return at > 0 && Date.now() - at < DISMISS_DAYS * 864e5;
    } catch {
        return false;
    }
};

export function installApp({ banner = false } = {}) {
    return {
        installed: isStandalone(),
        ios: isIos(),
        canPrompt: Boolean(deferredPrompt),
        showIosHelp: false,
        hidden: banner && dismissedRecently(),

        init() {
            listeners.add(() => { this.canPrompt = Boolean(deferredPrompt); });
            window.addEventListener('appinstalled', () => { this.installed = true; });
        },

        // Something useful to offer on this device.
        get available() {
            return !this.installed && !this.hidden && (this.canPrompt || this.ios);
        },

        async install() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                deferredPrompt = null;
                this.canPrompt = false;
                if (outcome === 'accepted') this.installed = true;

                return;
            }
            this.showIosHelp = true;
        },

        dismiss() {
            this.hidden = true;
            try {
                localStorage.setItem(DISMISS_KEY, String(Date.now()));
            } catch {
                // Private browsing: the banner simply shows again next time.
            }
        },
    };
}
