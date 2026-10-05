(() => {
    const panel = document.getElementById('wtcAnalyticsConsent');

    const message = document.getElementById('wtcAnalyticsConsentMessage');
    const actions = document.getElementById('wtcAnalyticsConsentActions');
    const storageKey = 'wtc_analytics_consent_v3';
    let choice = null;
    let eventQueue = [];
    let trackingStarted = false;
    let layoutShiftTotal = 0;
    let largestContentfulPaint = 0;
    let inputDelay = 0;
    let scrollThreshold = 25;

    try {
        choice = localStorage.getItem(storageKey);
    } catch {
        choice = null;
    }

    function sessionId() {
        try {
            let id = sessionStorage.getItem('wtc_analytics_session');
            if (!id) {
                if (crypto.randomUUID) {
                    id = crypto.randomUUID();
                } else {
                    const bytes = crypto.getRandomValues(new Uint8Array(16));
                    bytes[6] = (bytes[6] & 0x0f) | 0x40;
                    bytes[8] = (bytes[8] & 0x3f) | 0x80;
                    id = [...bytes].map((byte, index) => `${[4, 6, 8, 10].includes(index) ? '-' : ''}${byte.toString(16).padStart(2, '0')}`).join('');
                }
                sessionStorage.setItem('wtc_analytics_session', id);
            }
            return id;
        } catch {
            return '';
        }
    }

    function flushEvents(useBeacon = false) {
        if (choice !== 'accepted' || eventQueue.length === 0) return;
        const id = sessionId();
        if (!id) return;
        const batches = useBeacon ? Math.min(10, Math.ceil(eventQueue.length / 12)) : 1;
        for (let batch = 0; batch < batches && eventQueue.length > 0; batch += 1) {
            const events = eventQueue.splice(0, 12);
            const body = JSON.stringify({
                consent: 'accepted',
                sessionId: id,
                viewportWidth: window.innerWidth,
                events,
            });
            if (useBeacon && navigator.sendBeacon && navigator.sendBeacon('api/analytics.php', new Blob([body], { type: 'application/json' }))) continue;
            fetch('api/analytics.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body,
                keepalive: true,
            }).catch(() => {});
        }
    }

    function sendEvent(type, details = {}) {
        if (choice !== 'accepted') return;
        eventQueue.push({ type, page: window.location.pathname, ...details });
        if (eventQueue.length >= 12) flushEvents();
    }

    function startTracking() {
        if (trackingStarted) return;
        trackingStarted = true;
        let referrerHost = '';
        try {
            const referrer = document.referrer ? new URL(document.referrer) : null;
            if (referrer && referrer.origin !== window.location.origin) referrerHost = referrer.hostname;
        } catch {
        }
        sendEvent('page_view', { referrerHost });
        const trackLoadDuration = () => {
            const navigation = performance.getEntriesByType('navigation')[0];
            if (navigation?.loadEventEnd > 0) sendEvent('performance', { metricName: 'page_load_ms', metricValue: Math.min(navigation.loadEventEnd, 60000) });
        };
        if (document.readyState === 'complete') trackLoadDuration();
        else window.addEventListener('load', trackLoadDuration, { once: true });
        const firstPaint = performance.getEntriesByName('first-contentful-paint')[0];
        if (firstPaint) sendEvent('performance', { metricName: 'fcp_ms', metricValue: Math.min(firstPaint.startTime, 60000) });
        if (window.PerformanceObserver) {
            try {
                const paintObserver = new PerformanceObserver((entries) => {
                    for (const entry of entries.getEntries()) largestContentfulPaint = Math.max(largestContentfulPaint, entry.startTime);
                });
                paintObserver.observe({ type: 'largest-contentful-paint', buffered: true });
                const shiftObserver = new PerformanceObserver((entries) => {
                    for (const entry of entries.getEntries()) {
                        if (!entry.hadRecentInput) layoutShiftTotal += entry.value;
                    }
                });
                shiftObserver.observe({ type: 'layout-shift', buffered: true });
                const inputObserver = new PerformanceObserver((entries) => {
                    for (const entry of entries.getEntries()) inputDelay = Math.max(inputDelay, entry.duration);
                });
                inputObserver.observe({ type: 'event', buffered: true, durationThreshold: 40 });
            } catch {
            }
        }
        window.setTimeout(() => {
            if (largestContentfulPaint > 0) sendEvent('performance', { metricName: 'lcp_ms', metricValue: Math.min(largestContentfulPaint, 60000) });
            if (inputDelay > 0) sendEvent('performance', { metricName: 'inp_ms', metricValue: Math.min(inputDelay, 60000) });
            sendEvent('performance', { metricName: 'cls', metricValue: Math.min(layoutShiftTotal, 10) });
            flushEvents();
        }, 10000);
        window.setTimeout(() => {
            if (document.visibilityState === 'visible') sendEvent('engagement');
        }, 15000);
    }

    function render() {
        const accepted = choice === 'accepted';
        if (panel) {
            panel.hidden = choice === 'accepted' || choice === 'rejected';
            if (message) message.hidden = false;
            if (actions) actions.hidden = false;
        }
        renderProfilePreference();
        if (accepted) startTracking();
    }

    function renderProfilePreference() {
        const status = document.getElementById('analyticsConsentStatus');
        if (!status) return;

        status.textContent = choice === 'accepted'
            ? 'Autorisé'
            : choice === 'rejected'
                ? 'Refusé'
                : 'Aucun choix';
        document.querySelectorAll('[data-analytics-consent-choice]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.analyticsConsentChoice === choice));
        });
    }

    function saveChoice(nextChoice) {
        choice = nextChoice;
        if (choice === 'rejected') {
            eventQueue = [];
            trackingStarted = false;
            try {
                sessionStorage.removeItem('wtc_analytics_session');
            } catch {
            }
        }
        try {
            localStorage.setItem(storageKey, choice);
        } catch {
        }
        render();
    }

    document.getElementById('wtcAnalyticsAccept')?.addEventListener('click', () => saveChoice('accepted'));
    document.getElementById('wtcAnalyticsReject')?.addEventListener('click', () => saveChoice('rejected'));
    document.querySelectorAll('[data-analytics-consent-choice]').forEach((button) => {
        button.addEventListener('click', () => {
            const nextChoice = button.dataset.analyticsConsentChoice;
            if (nextChoice === 'accepted' || nextChoice === 'rejected') saveChoice(nextChoice);
        });
    });
    document.addEventListener('click', (event) => {
        if (!event.isTrusted || choice !== 'accepted' || !(event.target instanceof Element)) return;
        const target = event.target.closest('a[href], button');
        if (!target || target.closest('#wtcAnalyticsConsent, #wtcAnalyticsPreferences, [data-analytics-consent-choice]')) return;
        if (target instanceof HTMLAnchorElement) {
            const url = new URL(target.href, window.location.href);
            if (!['http:', 'https:'].includes(url.protocol)) return;
            const path = url.pathname;
            const isDownload = /\.(pdf|zip|docx?|xlsx?|pptx?|jpe?g|png|webp|mp4)$/i.test(path);
            sendEvent(isDownload ? 'download' : 'click', {
                targetType: 'link',
                targetHost: url.origin === window.location.origin ? '' : url.hostname,
                targetPath: path,
                label: target.dataset.analyticsLabel || '',
            });
        } else {
            const label = target.dataset.analyticsLabel;
            if (label) sendEvent('click', { targetType: 'button', label });
        }
    });
    document.addEventListener('submit', (event) => {
        if (!event.isTrusted || choice !== 'accepted' || !(event.target instanceof HTMLFormElement)) return;
        const label = event.target.dataset.analyticsLabel;
        if (label) sendEvent('form_submit', { label });
    }, true);
    document.addEventListener('scroll', () => {
        if (choice !== 'accepted') return;
        const scrollable = document.documentElement.scrollHeight - window.innerHeight;
        if (scrollable <= 0) return;
        const progress = Math.min(100, Math.floor((window.scrollY / scrollable) * 100));
        while (scrollThreshold <= 100 && progress >= scrollThreshold) {
            sendEvent('scroll_depth', { scrollDepth: scrollThreshold });
            scrollThreshold += 25;
        }
    }, { passive: true });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') flushEvents(true);
    });
    window.addEventListener('pagehide', () => flushEvents(true));
    window.setInterval(() => flushEvents(), 10000);
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element) || !event.target.closest('#wtcAnalyticsPreferences')) return;
        choice = null;
        flushEvents(true);
        render();
        document.getElementById('wtcAnalyticsAccept')?.focus();
    });
    render();
})();
