(() => {
    const DISMISS_KEY = 'c2me-field-install-dismissed';
    const DISMISS_DAYS = 14;

    let deferredPrompt = null;

    const isStandalone = () =>
        window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    const installButton = () => document.getElementById('install-button');
    const installBanner = () => document.getElementById('install-banner');

    function inviteDismissed() {
        try {
            const until = Number(localStorage.getItem(DISMISS_KEY) ?? 0);

            return Number.isFinite(until) && until > Date.now();
        } catch {
            return false;
        }
    }

    function setInviteVisible(visible) {
        const button = installButton();
        const banner = installBanner();
        const show = visible && !isStandalone();

        if (button) {
            button.hidden = !show;
        }

        if (banner) {
            banner.hidden = !show || inviteDismissed();
        }
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        if (typeof event.prompt !== 'function') {
            return;
        }

        event.preventDefault();
        deferredPrompt = event;
        setInviteVisible(true);
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        setInviteVisible(false);
    });

    async function promptInstall() {
        if (!deferredPrompt) {
            return;
        }

        const prompt = deferredPrompt;
        deferredPrompt = null;

        try {
            await prompt.prompt();
            await prompt.userChoice;
        } catch {
            // L'utilisateur peut installer depuis le menu du navigateur.
        }

        setInviteVisible(false);
    }

    function dismissInvite() {
        try {
            localStorage.setItem(
                DISMISS_KEY,
                String(Date.now() + DISMISS_DAYS * 86400000),
            );
        } catch {
            // Le rappel réapparaîtra au prochain chargement.
        }

        const banner = installBanner();

        if (banner) {
            banner.hidden = true;
        }
    }

    document.addEventListener('click', (event) => {
        const target = event.target.closest('#install-button, #install-prompt, #install-dismiss');

        if (!target) {
            return;
        }

        if (target.id === 'install-dismiss') {
            dismissInvite();

            return;
        }

        promptInstall();
    });

    if (isStandalone()) {
        setInviteVisible(false);
    }

    function setVersion(version) {
        const element = document.getElementById('app-version');

        if (element) {
            element.textContent = `Version hors ligne : ${version}`;
        }
    }

    const declaredVersion = document
        .querySelector('meta[name="offline-version"]')
        ?.content ?? 'inconnue';

    setVersion(declaredVersion);

    if (!('serviceWorker' in navigator)) {
        return;
    }

    navigator.serviceWorker
        .register('/sw.js', { scope: '/' })
        .then(() => navigator.serviceWorker.ready)
        .then((registration) => {
            const worker = registration.active;

            if (!worker) {
                setVersion(declaredVersion);

                return;
            }

            const channel = new MessageChannel();

            channel.port1.onmessage = (message) => {
                if (message.data?.type === 'c2me-version') {
                    setVersion(message.data.version);
                }
            };

            worker.postMessage({ type: 'c2me-version' }, [channel.port2]);
        })
        .catch(() => {
            // L'application reste utilisable sans service worker.
            setVersion(declaredVersion);
        });
})();
