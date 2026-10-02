<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
dashboardSendHeaders();
dashboardStartSession();
$authenticated = dashboardIsAuthenticated();
$csrf = (string) ($_SESSION['csrf'] ?? '');
$loginError = (string) ($_SESSION['login_error'] ?? '');
unset($_SESSION['login_error']);

function dashboardEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0b0a">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="application-name" content="JCM Studio">
    <meta name="apple-mobile-web-app-title" content="JCM Studio">
    <title><?= $authenticated ? 'Vue générale' : 'Accès privé' ?> — JCM Studio</title>
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
    <link rel="mask-icon" href="assets/icons/icon.svg" color="#e7c56d">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link rel="stylesheet" href="assets/dashboard-premium.css?v=12">
    <link rel="stylesheet" href="assets/dashboard-admin.css?v=5">
    <script src="assets/pwa.js" defer></script>
    <?php if ($authenticated): ?>
        <script src="assets/dashboard.js?v=14" defer></script>
    <?php endif; ?>
</head>
<body class="<?= $authenticated ? 'dashboard-page' : 'login-page' ?>">
<?php if (!$authenticated): ?>
    <main class="login-shell">
        <form class="login-content" action="auth.php" method="post" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= dashboardEscape($csrf) ?>">
            <span class="login-mark" aria-hidden="true"><img src="assets/icons/icon.svg" alt=""></span>
            <h1>Connexion</h1>
            <label for="username">Identifiant</label>
            <input id="username" name="username" type="text" autocomplete="username" maxlength="128" required autofocus>
            <label for="password">Mot de passe</label>
            <input id="password" name="password" type="password" autocomplete="current-password" maxlength="1024" required>
            <?php if ($loginError !== ''): ?>
                <p class="login-error" role="alert"><?= dashboardEscape($loginError) ?></p>
            <?php endif; ?>
            <button class="button button-dark login-submit" type="submit">Se connecter</button>
        </form>
    </main>
<?php else: ?>
    <div class="app-shell">
        <aside class="rail">
            <a class="rail-brand" href="index.php" aria-label="Accueil JCM Studio"><img src="assets/icons/icon.svg" alt=""></a>
            <span class="rail-label">STUDIO</span>
            <span class="rail-active" aria-label="Vue générale">01</span>
            <span class="rail-spacer"></span>
            <span class="rail-live" aria-label="Connexion active"></span>
        </aside>
        <main class="main-content">
            <header class="topbar">
                <a class="topbar-brand" href="index.php">JCM <span>/ STUDIO</span></a>
                <div class="topbar-actions">
                    <span class="topbar-date" id="today-date"></span>
                    <form action="auth.php" method="post">
                        <input type="hidden" name="csrf" value="<?= dashboardEscape($csrf) ?>">
                        <input type="hidden" name="action" value="logout">
                        <button class="logout-button" type="submit" aria-label="Fermer la session">Quitter <span aria-hidden="true">↗</span></button>
                    </form>
                </div>
            </header>
            <section class="sites-screen" id="sites-screen" aria-labelledby="sites-title">
                <div class="sites-heading">
                    <div>
                        <p class="eyebrow"><span class="status-dot"></span> VOS SITES CONNECTÉS</p>
                        <h1 id="sites-title">Statistiques <span>web.</span></h1>
                    </div>
                    <p id="sites-feedback" role="status">Vérification de la connexion…</p>
                </div>
                <button class="site-entry" id="warriors-site" type="button">
                    <span class="site-entry__mark" aria-hidden="true">W</span>
                    <span class="site-entry__name"><strong>Warriors Training Club</strong><small>Site Warriors</small></span>
                    <span class="site-entry__status" id="warriors-status">Connexion…</span>
                    <span class="site-entry__arrow" aria-hidden="true">↗</span>
                </button>
            </section>

            <div id="site-detail" hidden>
            <section class="intro-row">
                <div>
                    <button class="back-button" id="back-to-sites" type="button"><span aria-hidden="true">←</span> Sites</button>
                    <p class="eyebrow"><span class="status-dot"></span> STATISTIQUES DU SITE <span class="eyebrow-divider">/</span> WTC</p>
                    <h1>Warriors, <span>en mouvement.</span></h1>
                </div>
                <a class="site-admin-link" href="admin.php"><span class="site-admin-link__index">ESPACE PRIVÉ</span><span>Administration</span><span class="site-admin-link__arrow" aria-hidden="true">↗</span></a>
            </section>
            <div class="data-line"><span id="data-status">Chargement des données…</span><span id="last-updated"></span></div>
            <div class="report-controls">
                <div class="segmented-control" role="group" aria-label="Période du rapport">
                    <button type="button" data-period="7" aria-pressed="false">7 j</button>
                    <button type="button" data-period="28" aria-pressed="true">28 j</button>
                    <button type="button" data-period="90" aria-pressed="false">90 j</button>
                </div>
                <span class="report-controls__note">Période d’observation</span>
            </div>

            <section class="metric-grid" aria-label="Indicateurs de fréquentation">
                <article class="metric metric-featured"><div class="metric-top"><span>PAGES / SESSION</span><span class="metric-mark">01</span></div><strong class="metric-value" id="pages-per-session">—</strong><span class="metric-note" data-period-note="average">profondeur moyenne · 28 j</span></article>
                <article class="metric"><div class="metric-top"><span>SESSIONS</span><span class="metric-mark">02</span></div><strong class="metric-value" id="sessions">—</strong><span class="metric-note" data-period-note="range">sur les 28 derniers jours</span></article>
                <article class="metric"><div class="metric-top"><span>VUES DE PAGE</span><span class="metric-mark">03</span></div><strong class="metric-value" id="page-views">—</strong><span class="metric-note" data-period-note="range">sur les 28 derniers jours</span></article>
                <article class="metric"><div class="metric-top"><span>CLICS</span><span class="metric-mark">04</span></div><strong class="metric-value" id="click-count">—</strong><span class="metric-note">liens et boutons</span></article>
                <article class="metric"><div class="metric-top"><span>ENGAGEMENT</span><span class="metric-mark">05</span></div><strong class="metric-value" id="engagement">—</strong><span class="metric-note">sessions actives ≥ 15 s</span></article>
                <article class="metric"><div class="metric-top"><span>FORMULAIRES</span><span class="metric-mark">06</span></div><strong class="metric-value" id="form-submissions">—</strong><span class="metric-note">soumissions · sans contenu saisi</span></article>
                <article class="metric"><div class="metric-top"><span>SORTANTS</span><span class="metric-mark">07</span></div><strong class="metric-value" id="outbound-clicks">—</strong><span class="metric-note">clics vers d’autres sites</span></article>
                <article class="metric"><div class="metric-top"><span>REBOND</span><span class="metric-mark">08</span></div><strong class="metric-value" id="bounce-rate">—</strong><span class="metric-note">sessions à une seule page</span></article>
            </section>

            <section class="analytics-grid">
                <article class="panel chart-panel">
                    <div class="panel-heading"><div><p class="eyebrow">TRAFIC DU SITE</p><h2>Les visites, jour après jour.</h2></div><div class="chart-tools"><div class="segmented-control segmented-control--compact" role="group" aria-label="Mesure affichée"><button type="button" data-chart-mode="views" aria-pressed="true">Vues</button><button type="button" data-chart-mode="sessions" aria-pressed="false">Sessions</button></div><span class="period-label" data-period-label>28 JOURS</span></div></div>
                    <div class="chart-wrap"><canvas id="traffic-chart" aria-label="Graphique des vues quotidiennes" role="img"></canvas><p class="empty-state" id="chart-empty" hidden>Données indisponibles.</p></div>
                    <div class="chart-legend"><span><i></i> <span id="chart-metric-label">Vues de page</span></span><span id="event-total">Événements : —</span></div>
                </article>
                <article class="panel pages-panel">
                    <div class="panel-heading"><div><p class="eyebrow">CONTENU</p><h2>Pages les plus consultées.</h2></div></div>
                    <div class="table-wrap"><table><thead><tr><th>PAGE</th><th>VUES</th></tr></thead><tbody id="top-pages"><tr><td colspan="2" class="table-placeholder">En attente des données…</td></tr></tbody></table></div>
                </article>
            </section>

            <section class="breakdown-grid" aria-label="Acquisition et actions">
                <article class="panel"><div class="panel-heading"><div><p class="eyebrow">INTERACTIONS</p><h2>Liens, boutons, fichiers.</h2></div><span class="period-label" data-period-label>28 JOURS</span></div><div class="table-wrap"><table><thead><tr><th>ACTION</th><th>VOLUME</th></tr></thead><tbody id="top-clicks"></tbody></table></div></article>
                <article class="panel"><div class="panel-heading"><div><p class="eyebrow">ACQUISITION</p><h2>Sources de visite.</h2></div></div><div class="table-wrap"><table><thead><tr><th>DOMAINE SOURCE</th><th>VUES</th></tr></thead><tbody id="top-sources"></tbody></table></div></article>
                <article class="panel"><div class="panel-heading"><div><p class="eyebrow">CONVERSION</p><h2>Soumissions de formulaires.</h2></div></div><div class="table-wrap"><table><thead><tr><th>FORMULAIRE</th><th>SOUMISSIONS</th></tr></thead><tbody id="top-forms"></tbody></table></div></article>
            </section>

            <section class="device-grid" aria-label="Répartition technique des sessions">
                <article class="panel"><div class="panel-heading"><div><p class="eyebrow">APPAREILS</p><h2>Type d’écran.</h2></div></div><div class="table-wrap"><table><thead><tr><th>CATÉGORIE</th><th>SESSIONS</th></tr></thead><tbody id="device-breakdown"></tbody></table></div></article>
                <article class="panel"><div class="panel-heading"><div><p class="eyebrow">NAVIGATEURS</p><h2>Logiciels utilisés.</h2></div></div><div class="table-wrap"><table><thead><tr><th>NAVIGATEUR</th><th>SESSIONS</th></tr></thead><tbody id="browser-breakdown"></tbody></table></div></article>
                <article class="panel"><div class="panel-heading"><div><p class="eyebrow">SYSTÈMES</p><h2>Environnements.</h2></div></div><div class="table-wrap"><table><thead><tr><th>SYSTÈME</th><th>SESSIONS</th></tr></thead><tbody id="os-breakdown"></tbody></table></div></article>
                <article class="panel"><div class="panel-heading"><div><p class="eyebrow">AFFICHAGE</p><h2>Tailles d’écran.</h2></div></div><div class="table-wrap"><table><thead><tr><th>FORMAT</th><th>SESSIONS</th></tr></thead><tbody id="viewport-breakdown"></tbody></table></div></article>
            </section>

            <section class="behavior-grid" aria-label="Comportement et performance">
                <article class="panel"><div class="panel-heading"><div><p class="eyebrow">LECTURE DE PAGE</p><h2>Profondeur de scroll.</h2></div></div><div class="table-wrap"><table><thead><tr><th>PROGRESSION</th><th>SESSIONS</th></tr></thead><tbody id="scroll-breakdown"></tbody></table></div></article>
                <article class="panel"><div class="panel-heading"><div><p class="eyebrow">EXPÉRIENCE WEB</p><h2>Performances mesurées.</h2></div><span class="period-label">MOYENNE</span></div><div class="table-wrap"><table><thead><tr><th>INDICATEUR</th><th>VALEUR</th></tr></thead><tbody id="performance-breakdown"></tbody></table></div></article>
                <article class="panel"><div class="panel-heading"><div><p class="eyebrow">RYTHME DE VISITE</p><h2>Heures les plus actives.</h2></div></div><div class="table-wrap"><table><thead><tr><th>HEURE LOCALE SERVEUR</th><th>VUES</th></tr></thead><tbody id="hour-breakdown"></tbody></table></div></article>
            </section>

            <section class="site-section">
                <div class="section-heading"><div><p class="eyebrow">ACTIVITÉ DU CLUB</p><h2>Au-delà du trafic.</h2></div><span class="section-caption">DONNÉES DE L’APPLICATION</span></div>
                <div class="club-grid">
                    <article class="club-stat"><span>MEMBRES</span><strong id="members">—</strong><small id="active-members-note">comptes enregistrés</small></article>
                    <article class="club-stat"><span>SÉANCES À VENIR</span><strong id="upcoming-sessions">—</strong><small>inscrites au planning</small></article>
                    <article class="club-stat"><span>INSCRIPTIONS</span><strong id="enrolments">—</strong><small>ce mois-ci</small></article>
                    <article class="club-stat"><span>CLASSEMENTS</span><strong id="rankings">—</strong><small>records enregistrés</small></article>
                    <article class="club-stat"><span>SIGNALEMENTS</span><strong id="reports">—</strong><small>reçus par l’application</small></article>
                    <article class="club-stat"><span>SÉANCES</span><strong id="monthly-sessions">—</strong><small>planifiées ce mois-ci</small></article>
                </div>
            </section>
            <footer class="app-footer"><span>JCM / STUDIO</span><span id="source-note">Mesure interne · rétention 90 jours</span><span>USAGE INTERNE</span></footer>
            </div>
        </main>
    </div>
<?php endif; ?>
</body>
</html>