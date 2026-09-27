<?php
define('WTC_DISABLE_SQL_ACTION_AUDIT', true);
require_once __DIR__ . '/includes/general/administration.php';

$querySections = [
    'SELECT' => 'Lectures',
    'INSERT' => 'Ajouts',
    'UPDATE' => 'Modifications',
    'DELETE' => 'Suppressions',
    'OTHER' => 'Autres',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actions SQL — Warriors Training Club</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607102000">
    <link rel="stylesheet" href="css/db-audit.css?v=4">
</head>
<body>
    <?php require __DIR__ . '/includes/general/navbar.php'; ?>

    <main class="sql-actions-page">
        <header class="sql-actions-heading">
            <div class="container">
                <p class="eyebrow">Administration</p>
                <div class="sql-actions-heading__row">
                    <div>
                        <h1>Actions SQL</h1>
                        <p class="sql-actions-subtitle">Requêtes exécutées sur l’application, tous utilisateurs confondus.</p>
                    </div>
                    <div class="sql-actions-live" id="sqlActionsConnection" role="status">
                        <span class="sql-actions-live__dot" aria-hidden="true"></span>
                        <span>Connexion…</span>
                    </div>
                </div>
            </div>
        </header>

        <section class="sql-actions-content container" aria-label="Journal des requêtes SQL">
            <div class="sql-actions-toolbar">
                <label class="sql-actions-filter" for="sqlActionsFilter">
                    <span>Type de requête</span>
                    <select id="sqlActionsFilter" class="form-select">
                        <?php foreach ($querySections as $type => $label): ?>
                            <option value="<?= $type ?>" data-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> (0)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="sql-actions-updated" id="sqlActionsUpdated">En attente des premières requêtes</div>
            </div>

            <?php foreach ($querySections as $type => $label): ?>
                <section class="sql-actions-panel<?= $type === 'SELECT' ? ' is-active' : '' ?>" role="tabpanel"
                    id="sqlPanel<?= $type ?>" aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"<?= $type === 'SELECT' ? '' : ' hidden' ?>>
                    <div class="sql-actions-feed" id="sqlRows<?= $type ?>" aria-live="polite">
                        <p class="sql-actions-empty">Aucune requête enregistrée pour le moment.</p>
                    </div>
                </section>
            <?php endforeach; ?>
            <p class="sql-actions-note">Les valeurs de paramètres et les littéraux sensibles sont masqués. Les 150 dernières requêtes par section sont affichées.</p>
        </section>
    </main>

    <div class="modal fade wtc-modal" id="sqlQueryModal" tabindex="-1" aria-labelledby="sqlQueryModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content wtc-modal__content">
                <div class="modal-header wtc-modal__header">
                    <h2 class="modal-title" id="sqlQueryModalTitle">Requête SQL</h2>
                    <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <pre class="sql-actions-modal-query" id="sqlQueryModalStatement"></pre>
                </div>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/includes/general/footer.php'; ?>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/db-audit.js?v=4" defer></script>
</body>
</html>