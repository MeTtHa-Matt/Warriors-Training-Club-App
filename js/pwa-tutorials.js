(function () {
    var deferredPrompt = null;

    function addManifestLink() {
        if (document.querySelector('link[rel="manifest"]')) return;
        var link = document.createElement('link');
        link.rel = 'manifest';
        link.href = 'manifest.json';
        document.head.appendChild(link);
    }

    function isInstalled() {
        var displayModes = ['fullscreen', 'standalone', 'minimal-ui', 'window-controls-overlay'];
        var hasInstalledDisplayMode = window.matchMedia && displayModes.some(function (mode) {
            return window.matchMedia('(display-mode: ' + mode + ')').matches;
        });
        return hasInstalledDisplayMode ||
            (window.navigator && window.navigator.standalone === true);
    }

    function isMobile() {
        return /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');
    }

    function showPopup() {
        var popup = document.getElementById('pwa-install-popup');
        if (popup && !isInstalled()) popup.style.display = 'flex';
    }

    addManifestLink();

    window.addEventListener('beforeinstallprompt', function (event) {
        if (!isMobile()) return;
        event.preventDefault();
        deferredPrompt = event;
        showPopup();
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        var popup = document.getElementById('pwa-install-popup');
        if (popup) popup.style.display = 'none';
    });

    document.addEventListener('DOMContentLoaded', function () {
        var popup = document.getElementById('pwa-install-popup');
        var closeButton = document.getElementById('pwa-install-close');
        var openButton = document.getElementById('pwa-install-open');
        if (!popup || !closeButton || !openButton || isInstalled()) return;

        closeButton.addEventListener('click', function () {
            popup.style.display = 'none';
        });

        openButton.addEventListener('click', async function () {
            if (!deferredPrompt) {
                window.location.href = 'tuto-install.php';
                return;
            }

            var promptEvent = deferredPrompt;
            deferredPrompt = null;
            popup.style.display = 'none';
            try {
                await promptEvent.prompt();
                var choice = await promptEvent.userChoice;
                if (!choice || choice.outcome !== 'accepted') {
                    window.location.href = 'tuto-install.php';
                }
            } catch (error) {
                window.location.href = 'tuto-install.php';
            }
        });

        if (isMobile()) showPopup();
    });
}());
