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

<button type="button" class="wtc-report-fab" data-bs-toggle="modal" data-bs-target="#reportHelpModal" aria-label="Signaler un problème" title="Signaler un problème">
    <i class="bi bi-exclamation-lg"></i>
</button>
<div class="modal fade wtc-modal" id="reportHelpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content wtc-modal__content">
            <div class="modal-header wtc-modal__header">
                <h5 class="modal-title">Besoin d’aide ?</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body"><p class="mb-0">Vous avez un problème ? Signalez-le :</p></div>
            <div class="modal-footer wtc-modal__footer">
                <a href="signalements.php" class="btn btn-wtc-gold w-100">Signaler un problème</a>
            </div>
        </div>
    </div>
</div>

<script>
const WTC_CACHE_BUST_VERSION = '202608262200';
const WTC_CACHE_BUSTER_KEY = 'wtc-cache-buster';

if (window.localStorage && window.localStorage.getItem(WTC_CACHE_BUSTER_KEY) !== WTC_CACHE_BUST_VERSION) {
    Promise.allSettled([
        navigator.serviceWorker?.getRegistrations?.().then((registrations) => Promise.all(registrations.map((reg) => reg.unregister()))),
        caches?.keys?.().then((keys) => Promise.all(keys.map((key) => caches.delete(key))))
    ]).finally(() => {
        window.localStorage.setItem(WTC_CACHE_BUSTER_KEY, WTC_CACHE_BUST_VERSION);
        window.location.reload();
    });
}

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
<?php
// Global session timeout modal and script included on every page when user is logged in
if (!empty($_SESSION['user_id'])):
    require_once __DIR__ . '/app-settings.php';
    $createdAt = (int) ($_SESSION['created_at'] ?? time());
    $timeoutSeconds = (int) get_setting('session_timeout_seconds', (string)(14 * 86400));
    $modalThreshold = (int) get_setting('session_modal_threshold_seconds', (string)(24 * 3600));
?>

<!-- Session timeout modal (global) -->
<div class="modal fade wtc-modal" id="sessionTimeoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content wtc-modal__content">
            <div class="modal-header wtc-modal__header">
                <h5 class="modal-title">Avertissement de déconnexion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <p id="sessionTimeoutMessage">Tu seras déconnecté dans</p>
                <div style="font-size:1.6rem;font-weight:700;color:var(--gold);margin-bottom:0.5rem;" id="sessionTimeoutCountdown">00:00:00</div>
                <p>Tu peux te reconnecter maintenant pour prolonger ta session.</p>
            </div>
            <div class="modal-footer wtc-modal__footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">J'ai bien compris</button>
                <button type="button" id="sessionTimeoutLogout" class="btn btn-wtc-gold">Se reconnecter</button>
            </div>
        </div>
    </div>
</div>

<script>
// session timeout script available on all pages
(function(){
    const createdAt = <?= json_encode($createdAt) ?> * 1000; // ms
    const timeoutMs = <?= json_encode($timeoutSeconds) ?> * 1000; // ms
    const thresholdMs = <?= json_encode($modalThreshold) ?> * 1000; // ms
    let modalShown = false;

    function formatSeconds(secs){
        if (secs <= 0) return '0s';
        const units = [
            ['year',31557600],['month',2592000],['week',604800],['day',86400],['hour',3600],['minute',60],['second',1]
        ];
        for (const [name, val] of units){
            if (secs >= val){
                const v = Math.floor(secs / val);
                return v + ' ' + name + (v>1? 's':'');
            }
        }
        return secs + 's';
    }

    function pad(n){ return n.toString().padStart(2,'0'); }

    function formatCountdown(ms){
        const total = Math.max(0, Math.floor(ms/1000));
        const days = Math.floor(total / 86400);
        let rem = total % 86400;
        const hours = Math.floor(rem / 3600); rem = rem % 3600;
        const minutes = Math.floor(rem / 60);
        const seconds = rem % 60;
        const hh = pad(hours);
        const mm = pad(minutes);
        const ss = pad(seconds);
        if (days > 0) return days + 'j ' + hh + ':' + mm + ':' + ss;
        return hh + ':' + mm + ':' + ss;
    }

    function checkTimeout(){
        const now = Date.now();
        const expiresAt = createdAt + timeoutMs;
        const remainingMs = Math.max(0, expiresAt - now);

        if (remainingMs <= 0) {
            window.location.href = 'includes/account/deconnexion_process.php?next=connexion.php';
            return;
        }

        if (!modalShown && thresholdMs > 0 && remainingMs <= thresholdMs) {
            modalShown = true;
            const el = document.getElementById('sessionTimeoutCountdown');
            if (el) el.textContent = formatCountdown(remainingMs);
            const modalEl = document.getElementById('sessionTimeoutModal');
            (function whenBootstrapReady(fn){
                if (window.bootstrap && window.bootstrap.Modal) return fn();
                const t = setInterval(function(){
                    if (window.bootstrap && window.bootstrap.Modal){ clearInterval(t); fn(); }
                }, 50);
                setTimeout(function(){ clearInterval(t); }, 5000);
            })(function(){
                const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            });

            const logoutBtn = document.getElementById('sessionTimeoutLogout');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function(){
                    window.location.href = 'includes/account/deconnexion_process.php?next=connexion.php';
                });
            }
        }

        if (modalShown) {
            const el2 = document.getElementById('sessionTimeoutCountdown');
            if (el2) el2.textContent = formatCountdown(remainingMs);
        }
    }

    checkTimeout();
    setInterval(checkTimeout, 1000);
})();
</script>

<?php endif; ?>
