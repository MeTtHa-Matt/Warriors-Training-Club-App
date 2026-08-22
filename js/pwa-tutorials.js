document.addEventListener('DOMContentLoaded', function(){
    try{
        var ua = navigator.userAgent || '';
        var isMobile = /Mobi|Android|iPhone|iPad|iPod/i.test(ua);
        if (!isMobile) return; // only mobile

        var isBrowser = /Mozilla|Chrome|Safari|Firefox|CriOS|FxiOS/i.test(ua);
        if (!isBrowser) return;

        var isInstalled = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || (window.navigator && window.navigator.standalone === true);
        if (isInstalled) return;

        var popup = document.getElementById('pwa-install-popup');
        if (!popup) return;
        popup.style.display = 'flex';

        document.getElementById('pwa-install-close').addEventListener('click', function(){
            popup.style.display = 'none';
        });

        document.getElementById('pwa-install-open').addEventListener('click', function(){
            // go to installer page
            window.location.href = 'tuto-install.php';
        });
    }catch(e){ /* ignore errors */ }
});
