<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<footer class="wtc-footer">
    <div class="container">
        <div class="wtc-footer__grid">

            <div class="wtc-footer__col">
                <p class="wtc-footer__brand"><span class="accent">Warriors</span> Training Club</p>
                <p class="wtc-footer__baseline">Cardio Training · Hyrox · Running-Trail · Préparation physique</p>
            </div>

            <div class="wtc-footer__col">
                <p class="wtc-footer__title">Contact</p>
                <p class="wtc-footer__line">
                    <i class="bi bi-geo-alt"></i>
                    Dojo Teddy Riner, 77720 Mormant
                </p>
                <p class="wtc-footer__line">
                    <a href="tel:0640670877"><i class="bi bi-telephone"></i> 06 40 67 08 77</a>
                </p>
            </div>

            <div class="wtc-footer__col">
                <p class="wtc-footer__title">Suivez-nous</p>
                <div class="wtc-footer__socials">
                    <a href="https://www.instagram.com/warriorstrainingclub/" target="_blank" rel="noopener" aria-label="Instagram" class="wtc-footer__social">
                        <i class="bi bi-instagram"></i>
                    </a>
                </div>
            </div>

        </div>

        <div class="wtc-footer__bottom">
            <p class="mb-0">&copy; <?= date('Y'); ?> Warriors Training Club. Tous droits réservés.</p>
            <p class="mb-0">Site réalisé par <span class="accent"><a href="ilyc.php">Matthew</a></span></p>
        </div>
    </div>
</footer>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        const basePath = window.location.pathname.replace(/\/[^\/]*$/, '') || '/';
        const path = basePath === '/' ? './sw.js' : `${basePath}/sw.js`;
        const scope = basePath === '/' ? './' : `${basePath}/`;
        navigator.serviceWorker.register(path, { scope })
            .then(reg => console.log('WTC service worker registered:', reg.scope))
            .catch(err => console.warn('WTC service worker registration failed:', err));
    });
}
</script>