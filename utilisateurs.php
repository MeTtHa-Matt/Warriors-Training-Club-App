<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require 'includes/general/db.php';

include __DIR__ . "/includes/general/users.php"
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Warriors Training Club - Utilisateurs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607051">
    <link rel="icon" type="image/png" href="/WTC-App/img/wtc.png">
</head>
<body>

<?php require 'includes/general/navbar.php'; ?>

<section class="hero hero--compact">
    <div class="container">
        <div class="row">
            <div class="col-12 col-lg-8">
                <span class="hero-badge mb-3"><span class="dot"></span>Administration</span>
                <h1 class="mt-3 mb-3">Gestion des <span class="accent">utilisateurs</span></h1>
                <p class="lead"><?= count($users) ?> compte<?= count($users) > 1 ? 's' : '' ?> enregistré<?= count($users) > 1 ? 's' : '' ?> sur le club.</p>
            </div>
        </div>
    </div>
</section>

<div class="section section--compact">
    <div class="container mb-4">
        <a href="administration.php" class="btn btn-wtc-outline rounded-pill">
            <i class="bi bi-arrow-left me-2"></i>Retour à l'administration
        </a>
    </div>
</div>

<section class="section" id="utilisateurs">
    <div class="container">

        <?php if (!empty($errors)): ?>
            <div class="auth-alert auth-alert--error">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-1"><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-alert auth-alert--success">
                <p class="mb-0"><?= htmlspecialchars($success) ?></p>
            </div>
        <?php endif; ?>

        <div class="users-maintenance-card mb-4">
            <div>
                <div class="users-maintenance-card__title">Mode maintenance</div>
                <p class="users-maintenance-card__text">
                    <?= $maintenanceEnabled ? 'Le site est actuellement en maintenance.' : 'Le site est actuellement accessible.' ?>
                </p>
            </div>
            <form method="POST" action="maintenance.php" class="d-inline">
                <input type="hidden" name="enable" value="<?= $maintenanceEnabled ? '0' : '1' ?>">
                <button type="submit" class="btn <?= $maintenanceEnabled ? 'btn-outline-light' : 'btn-wtc-gold' ?> rounded-pill">
                    <i class="bi <?= $maintenanceEnabled ? 'bi-toggle-on' : 'bi-toggle-off' ?> me-2"></i>
                    <?= $maintenanceEnabled ? 'Désactiver la maintenance' : 'Activer la maintenance' ?>
                </button>
            </form>
        </div>

        <div class="users-toolbar mb-4">
            <input type="text" id="userSearch" class="form-control auth-input users-search"
                   placeholder="Rechercher un utilisateur (nom, prénom, email)...">
        </div>

        <div class="users-list-wrapper" id="usersTable">
            <div class="users-list-header d-none d-md-flex">
                <span class="users-list-header__cell">Utilisateur</span>
                <span class="users-list-header__cell">Email</span>
                <span class="users-list-header__cell">Rôles</span>
                <span class="users-list-header__cell users-list-header__cell--actions">Actions</span>
            </div>

            <?php foreach ($users as $user): ?>
                <article class="users-card <?= $user['ban'] ? 'users-row--banned' : '' ?>">
                    <div class="users-card__main">
                        <img src="img/pdps/<?= htmlspecialchars($user['pdp']) ?>"
                             alt="" class="users-avatar" width="40" height="40" loading="lazy" decoding="async">
                        <div class="users-card__identity" data-search>
                            <div class="users-name">
                                <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>
                                <?php if ((int) $user['id'] === (int) $currentId): ?>
                                    <span class="users-you">(toi)</span>
                                <?php endif; ?>
                            </div>
                            <div class="users-email"><?= htmlspecialchars($user['email']) ?></div>
                        </div>
                    </div>

                    <div class="users-badges">
                        <?php if ($user['admin']): ?>
                            <span class="users-badge users-badge--admin">Admin</span>
                        <?php endif; ?>
                        <?php if ($user['gerer_seances']): ?>
                            <span class="users-badge users-badge--coach">Gère les séances</span>
                        <?php endif; ?>
                        <?php if ($user['ban']): ?>
                            <span class="users-badge users-badge--ban">Banni</span>
                        <?php endif; ?>
                        <?php if (!$user['admin'] && !$user['gerer_seances'] && !$user['ban']): ?>
                            <span class="users-badge users-badge--member">Membre</span>
                        <?php endif; ?>
                    </div>

                    <div class="users-actions">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="target_id" value="<?= (int) $user['id'] ?>">
                            <input type="hidden" name="toggle_action" value="admin">
                            <button type="submit"
                                class="users-action-btn <?= $user['admin'] ? 'users-action-btn--active-admin' : '' ?>"
                                title="<?= $user['admin'] ? 'Retirer les droits admin' : 'Rendre admin' ?>"
                                <?= (int) $user['id'] === (int) $currentId ? 'disabled' : '' ?>>
                                <i class="bi bi-shield-lock"></i>
                                <span><?= $user['admin'] ? 'Retirer admin' : 'Rendre admin' ?></span>
                            </button>
                        </form>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="target_id" value="<?= (int) $user['id'] ?>">
                            <input type="hidden" name="toggle_action" value="gerer_seances">
                            <button type="submit"
                                class="users-action-btn <?= $user['gerer_seances'] ? 'users-action-btn--active-coach' : '' ?>"
                                title="<?= $user['gerer_seances'] ? 'Retirer la gestion des séances' : 'Autoriser la gestion des séances' ?>">
                                <i class="bi bi-calendar2-check"></i>
                                <span><?= $user['gerer_seances'] ? 'Retirer séances' : 'Gérer séances' ?></span>
                            </button>
                        </form>
                        <form method="POST" action="ban.php" class="d-inline">
                            <input type="hidden" name="target_id" value="<?= (int) $user['id'] ?>">
                            <button type="submit"
                                class="users-action-btn users-action-btn--danger <?= $user['ban'] ? 'users-action-btn--active-ban' : '' ?>"
                                title="<?= $user['ban'] ? 'Débannir' : 'Bannir' ?>"
                                <?= (int) $user['id'] === (int) $currentId ? 'disabled' : '' ?>>
                                <i class="bi <?= $user['ban'] ? 'bi-person-check' : 'bi-person-slash' ?>"></i>
                                <span><?= $user['ban'] ? 'Débannir' : 'Bannir' ?></span>
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if (empty($users)): ?>
                <p class="upcoming-empty text-center py-4">Aucun utilisateur enregistré.</p>
            <?php endif; ?>
        </div>

    </div>
</section>

<?php require 'includes/general/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('userSearch').addEventListener('input', function (e) {
        const query = e.target.value.trim().toLowerCase();
        document.querySelectorAll('#usersTable .users-card').forEach(function (card) {
            const text = Array.from(card.querySelectorAll('[data-search]'))
                .map(function (el) { return el.textContent.toLowerCase(); })
                .join(' ');
            card.style.display = text.includes(query) ? '' : 'none';
        });
    });
</script>

</body>
</html>
