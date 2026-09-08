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
        return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
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

        openButton.addEventListener('click', function () {
            window.location.href = 'tuto-install.php';
        });

        if (isMobile()) showPopup();
    });
}());
