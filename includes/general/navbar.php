<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current = basename($_SERVER['PHP_SELF']);
function wtc_active($page, $current)
{
    return $page === $current ? 'active' : '';
}

$isLoggedIn = isset($_SESSION['user_id']);
?>
<?php if ((int) ($_SESSION['admin'] ?? 0) === 1): ?>
    <link rel="stylesheet" href="css/admin-menu.css?v=202610281100">
<?php endif; ?>
<nav class="navbar navbar-expand-lg wtc-navbar sticky-top py-3">
    <div class="container">

        <a class="navbar-brand p-0" href="index.php">
            <img src="img/wtc.png" alt="Logo Warriors Training Club" class="wtc-navbar__logo">
        </a>

        <button class="navbar-toggler wtc-navbar__toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#wtcNavMenu" aria-controls="wtcNavMenu" aria-expanded="false" aria-label="Ouvrir le menu">
            <span class="bi bi-list"></span>
        </button>

        <div class="collapse navbar-collapse" id="wtcNavMenu">

            <ul class="navbar-nav mx-lg-auto my-3 my-lg-0 gap-lg-4 text-center">
                <li class="nav-item">
                    <a class="nav-link <?php echo wtc_active('index.php', $current); ?>" href="index.php">Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo wtc_active('seances.php', $current); ?>"
                        href="seances.php">Séances</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo wtc_active('classement.php', $current); ?>"
                        href="classement.php">Classement</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo wtc_active('reglement-interieur.php', $current); ?>"
                        href="reglement-interieur.php">Règlement intérieur</a>
                </li>
                <?php if ((int) ($_SESSION['admin'] ?? 0) === 1): ?>
                    <?php
                    $adminMenuGroups = [
                        'Utilisateurs' => [
                        ['url' => 'utilisateurs.php', 'icon' => 'bi bi-people', 'label' => 'Utilisateurs'],
                        ['url' => 'envoyer-mail.php', 'icon' => 'bi bi-envelope', 'label' => 'Envoyer un mail'],
                        ['url' => 'reports.php', 'icon' => 'bi bi-chat-left-text', 'label' => 'Signalements'],
                        ],
                        'Paramètres' => [
                        ['url' => 'liens-index.php', 'icon' => 'bi bi-link-45deg', 'label' => 'Liens accueil'],
                        ['url' => 'commits-dashboard.php', 'icon' => 'bi bi-git', 'label' => 'GitHub commits'],
                        ['url' => 'actions-sql.php', 'icon' => 'bi bi-journal-text', 'label' => 'Actions SQL'],
                        ['url' => 'settings.php', 'icon' => 'bi bi-gear', 'label' => 'Paramètres'],
                        ],
                    ];
                    ?>
                    <?php
                    $adminDropdownActive = in_array($current, array_merge(...array_map(
                        static fn(array $items): array => array_column($items, 'url'),
                        array_values($adminMenuGroups)
                    )), true);
                    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= $adminDropdownActive ? 'active' : '' ?>"
                            href="#" id="adminMenuDropdown" role="button"
                            aria-expanded="false">Administration</a>
                        <ul class="dropdown-menu dropdown-menu-lg-end wtc-user-menu" aria-labelledby="adminMenuDropdown">
                            <?php foreach ($adminMenuGroups as $groupLabel => $adminMenuItems): ?>
                                <?php $groupActive = in_array($current, array_column($adminMenuItems, 'url'), true); ?>
                                <li class="dropdown-submenu">
                                    <a class="dropdown-item dropdown-toggle wtc-user-menu__item <?= $groupActive ? 'active' : '' ?>"
                                        href="#" id="adminMenu<?= htmlspecialchars(str_replace(' ', '', $groupLabel)) ?>"
                                        role="button" aria-expanded="false">
                                        <?= htmlspecialchars($groupLabel) ?>
                                    </a>
                                    <ul class="dropdown-menu wtc-user-menu"
                                        aria-labelledby="adminMenu<?= htmlspecialchars(str_replace(' ', '', $groupLabel)) ?>">
                                        <?php foreach ($adminMenuItems as $item): ?>
                                            <li>
                                                <a class="dropdown-item wtc-user-menu__item <?= $current === $item['url'] ? 'active' : '' ?>"
                                                    href="<?= htmlspecialchars($item['url']) ?>">
                                                    <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                                                    <?= htmlspecialchars($item['label']) ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>

            <?php if ($isLoggedIn): ?>

                <div class="dropdown wtc-navbar__auth wtc-navbar__auth--user">
                    <button class="wtc-user-toggle" type="button" id="wtcUserMenu" data-bs-toggle="dropdown"
                        aria-expanded="false">
                        <?php $sessionPdp = !empty($_SESSION['pdp']) ? basename($_SESSION['pdp']) : 'pdp_base.png'; ?>
                        <img src="img/pdps/<?php echo htmlspecialchars($sessionPdp); ?>?v=<?php echo time(); ?>" alt="Photo de profil"
                            class="wtc-user-toggle__pdp">
                        <span class="wtc-user-toggle__name d-none d-lg-inline">
                            <?php echo htmlspecialchars($_SESSION['firstname']); ?>
                        </span>
                        <i class="bi bi-chevron-down wtc-user-toggle__chevron d-none d-lg-inline"></i>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end wtc-user-menu" aria-labelledby="wtcUserMenu">
                        <li>
                            <a class="dropdown-item wtc-user-menu__item" href="modifier-profil.php">
                                <i class="bi bi-pencil-square"></i>Modifier le profil
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item wtc-user-menu__item <?php echo wtc_active('whatsapp.php', $current); ?>"
                                href="whatsapp.php">
                                <i class="bi bi-whatsapp"></i>Rejoindre le groupe WhatsApp
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider wtc-user-menu__divider">
                        </li>

                        <li>
                            <a class="dropdown-item wtc-user-menu__item" href="includes/account/deconnexion_process.php">
                                <i class="bi bi-box-arrow-right"></i>Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>

            <?php else: ?>

                <div class="d-flex flex-column flex-lg-row gap-2 wtc-navbar__auth">
                    <a href="inscription.php" class="btn btn-wtc-gold rounded-pill px-4">Inscription</a>
                    <a href="connexion.php" class="btn btn-wtc-outline rounded-pill px-4">Connexion</a>
                </div>

            <?php endif; ?>

        </div>
    </div>
</nav>
<?php if ((int) ($_SESSION['admin'] ?? 0) === 1): ?>
<script>
(() => {
    const adminToggle = document.getElementById('adminMenuDropdown');
    const adminDropdown = adminToggle?.closest('.nav-item.dropdown');
    const adminMenu = adminToggle?.nextElementSibling;
    if (!adminToggle || !adminDropdown || !adminMenu) return;

    const closeAdminMenu = () => {
        adminMenu.classList.remove('show');
        adminToggle.setAttribute('aria-expanded', 'false');
        adminMenu.querySelectorAll('.dropdown-submenu > .dropdown-menu.show').forEach((submenu) => {
            submenu.classList.remove('show');
            submenu.previousElementSibling.setAttribute('aria-expanded', 'false');
        });
    };

    adminToggle.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const isOpening = !adminMenu.classList.contains('show');
        closeAdminMenu();
        if (isOpening) {
            adminMenu.classList.add('show');
            adminToggle.setAttribute('aria-expanded', 'true');
        }
    });

    adminMenu.addEventListener('click', (event) => {
        const groupToggle = event.target.closest('.dropdown-submenu > .dropdown-toggle');
        if (!groupToggle) return;

        event.preventDefault();
        event.stopPropagation();
        const submenu = groupToggle.nextElementSibling;
        const isOpening = !submenu.classList.contains('show');
        adminMenu.querySelectorAll('.dropdown-submenu > .dropdown-menu.show').forEach((openMenu) => {
            openMenu.classList.remove('show');
            openMenu.previousElementSibling.setAttribute('aria-expanded', 'false');
        });
        if (isOpening) {
            submenu.classList.add('show');
            groupToggle.setAttribute('aria-expanded', 'true');
        }
    });

    document.addEventListener('click', (event) => {
        if (!adminDropdown.contains(event.target)) closeAdminMenu();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && adminMenu.classList.contains('show')) {
            closeAdminMenu();
            adminToggle.focus();
        }
    });
})();
</script>
<?php endif; ?>
    <aside class="wtc-analytics-consent" id="wtcAnalyticsConsent" aria-label="Préférences de mesure d’audience" hidden>
        <div class="wtc-analytics-consent__message" id="wtcAnalyticsConsentMessage">
            <strong>Mesure d’audience</strong>
            <p>Les pages et actions sont associées à votre compte si vous êtes connecté, ou à une session anonyme sinon. Aucun contenu saisi n’est enregistré. Vous pourrez modifier ce choix à tout moment.</p>
        </div>
        <div class="wtc-analytics-consent__actions" id="wtcAnalyticsConsentActions">
            <button class="btn btn-sm btn-wtc-outline" id="wtcAnalyticsReject" type="button">Refuser</button>
            <button class="btn btn-sm btn-wtc-gold" id="wtcAnalyticsAccept" type="button">Accepter</button>
        </div>
    </aside>
<script src="assets/analytics-consent.js?v=12" defer></script>