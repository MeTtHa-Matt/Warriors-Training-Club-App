<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
dashboardSendHeaders();
dashboardStartSession();
if (!dashboardIsAuthenticated()) {
    header('Location: index.php', true, 303);
    exit;
}

function dashboardAdminEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$csrf = (string) ($_SESSION['csrf'] ?? '');
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0b0a">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="application-name" content="JCM Studio">
    <title>Administration — JCM Studio</title>
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
    <link rel="mask-icon" href="assets/icons/icon.svg" color="#e7c56d">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link rel="stylesheet" href="assets/dashboard-premium.css?v=12">
    <link rel="stylesheet" href="assets/dashboard-admin.css?v=5">
    <script src="assets/dashboard-admin.js?v=4" defer></script>
</head>
<body class="dashboard-page admin-page" data-csrf="<?= dashboardAdminEscape($csrf) ?>">
    <div class="app-shell">
        <aside class="rail">
            <a class="rail-brand" href="index.php" aria-label="Accueil JCM Studio"><img src="assets/icons/icon.svg" alt=""></a>
            <span class="rail-label">STUDIO</span>
            <span class="rail-active" aria-label="Administration">AD</span>
            <span class="rail-spacer"></span>
            <span class="rail-live" aria-label="Connexion active"></span>
        </aside>
        <main class="main-content admin-content">
            <header class="topbar">
                <a class="topbar-brand" href="index.php">JCM <span>/ STUDIO</span></a>
                <div class="topbar-actions">
                    <a class="admin-analytics-link" href="index.php?view=analytics">Vue analytics <span aria-hidden="true">↗</span></a>
                    <form action="auth.php" method="post">
                        <input type="hidden" name="csrf" value="<?= dashboardAdminEscape($csrf) ?>">
                        <input type="hidden" name="action" value="logout">
                        <button class="logout-button" type="submit">Quitter <span aria-hidden="true">↗</span></button>
                    </form>
                </div>
            </header>

            <header class="admin-heading">
                <div>
                    <p class="eyebrow"><span class="status-dot"></span> CONSOLE PRIVÉE <span class="eyebrow-divider">/</span> WTC</p>
                    <h1>Administration <span>du club.</span></h1>
                </div>
                <p id="admin-feedback" role="status" aria-live="polite">Chargement des données…</p>
            </header>

            <nav class="admin-tabs" aria-label="Outils d’administration" role="tablist">
                <button type="button" role="tab" aria-selected="true" data-section="overview">Vue générale</button>
                <button type="button" role="tab" aria-selected="false" data-section="users">Utilisateurs</button>
                <button type="button" role="tab" aria-selected="false" data-section="reports">Signalements</button>
                <button type="button" role="tab" aria-selected="false" data-section="audit">Actions SQL</button>
                <button type="button" role="tab" aria-selected="false" data-section="mail">Envoyer un mail</button>
                <button type="button" role="tab" aria-selected="false" data-section="links">Liens d’accueil</button>
                <button type="button" role="tab" aria-selected="false" data-section="commits">Commits GitHub</button>
                <button type="button" role="tab" aria-selected="false" data-section="settings">Paramètres</button>
            </nav>

            <section class="admin-section" data-view="overview" aria-label="Vue générale">
                <div class="admin-metrics" id="overview-metrics" aria-live="polite"></div>
                <div class="admin-toolbar">
                    <div><p class="eyebrow">ACCÈS AU SITE</p><h2>Mode maintenance</h2></div>
                    <label class="admin-switch"><input id="maintenance-toggle" type="checkbox"><span></span><b id="maintenance-label">Désactivé</b></label>
                </div>
            </section>

            <section class="admin-section" data-view="users" hidden>
                <div class="admin-toolbar">
                    <div><p class="eyebrow">COMPTES ET DROITS</p><h2>Gestion des utilisateurs</h2></div>
                    <input class="admin-search" id="user-search" type="search" placeholder="Rechercher un membre" aria-label="Rechercher un membre">
                </div>
                <div class="admin-users-list" id="users-list" aria-live="polite"></div>
            </section>

            <section class="admin-section" data-view="reports" hidden>
                <div class="admin-toolbar"><div><p class="eyebrow">MESSAGES VÉRIFIÉS</p><h2>Boîte des signalements</h2></div><span class="admin-count" id="reports-count"></span></div>
                <div class="admin-record-list" id="reports-list"></div>
            </section>

            <section class="admin-section" data-view="audit" hidden>
                <div class="admin-toolbar"><div><p class="eyebrow">ACTIVITÉ RÉCENTE</p><h2>Journal des actions SQL</h2></div></div>
                <div class="admin-record-list admin-audit-list" id="audit-list" aria-live="polite"></div>
            </section>

            <section class="admin-section" data-view="mail" hidden>
                <div class="admin-toolbar"><div><p class="eyebrow">MEMBRES OPT-IN</p><h2>Message collectif</h2></div><span class="admin-count" id="recipient-count">Destinataires actifs</span></div>
                <form id="mail-form" class="admin-form" enctype="multipart/form-data">
                    <label>Objet<input name="subject" maxlength="180" required></label>
                    <label>Style<select name="style_preset"><option value="classique">Classique</option><option value="chaleureux">Chaleureux</option><option value="professionnel">Professionnel</option><option value="energetique">Énergique</option></select></label>
                    <div class="admin-form-wide admin-editor-field"><span>Message</span><div class="admin-editor-toolbar" role="toolbar" aria-label="Mise en forme du message"><button type="button" data-editor-command="bold" aria-label="Gras"><strong>B</strong></button><button type="button" data-editor-command="italic" aria-label="Italique"><em>I</em></button><button type="button" data-editor-command="underline" aria-label="Souligné"><u>U</u></button><button type="button" data-editor-command="insertUnorderedList" aria-label="Liste à puces">Liste</button><button type="button" data-editor-command="createLink" aria-label="Ajouter un lien">Lien</button></div><div id="mail-editor" class="admin-editor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Message du courriel"></div><input type="hidden" name="message_html" id="message-html"></div>
                    <label>Signature<input name="signature" value="L’équipe du club" maxlength="120" required></label>
                    <label>Pièces jointes<input name="attachments[]" type="file" multiple><small>Maximum 5 fichiers, 4 Mo par fichier.</small></label>
                    <button class="button button-dark" type="submit">Envoyer aux membres autorisés <span aria-hidden="true">↗</span></button>
                </form>
            </section>

            <section class="admin-section" data-view="links" hidden>
                <div class="admin-toolbar"><div><p class="eyebrow">PAGE D’ACCUEIL</p><h2>Liens publics du site</h2></div></div>
                <form id="links-form" class="admin-form admin-form-links"></form>
            </section>

            <section class="admin-section" data-view="commits" hidden>
                <div class="admin-toolbar"><div><p class="eyebrow">DÉPÔT WARRIORS</p><h2>Historique GitHub</h2></div><button class="button button-outline" id="refresh-commits" type="button">Actualiser <span aria-hidden="true">↻</span></button></div>
                <div class="admin-record-list" id="commits-list"></div>
            </section>

            <section class="admin-section" data-view="settings" hidden>
                <div class="admin-toolbar"><div><p class="eyebrow">COMPORTEMENT DU SITE</p><h2>Paramètres d’application</h2></div></div>
                <form id="settings-form" class="admin-form">
                    <label>Déconnexion automatique<input name="session_timeout_seconds" type="number" min="1" max="315360000" required><small>Valeur en secondes. Valeur initiale : 14 jours.</small></label>
                    <label>Seuil avant l’alerte de déconnexion<input name="session_modal_threshold_seconds" type="number" min="0" max="315360000" required><small>Valeur en secondes. 0 désactive l’alerte.</small></label>
                    <button class="button button-dark" type="submit">Enregistrer</button>
                </form>
            </section>

            <footer class="app-footer"><span>JCM / STUDIO</span><span>Administration · Accès interne</span><span id="admin-updated"></span></footer>
        </main>
    </div>
</body>
</html>