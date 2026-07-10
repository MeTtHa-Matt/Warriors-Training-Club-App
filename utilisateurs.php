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
    <link rel="stylesheet" href="css/style.css?v=202607102200">
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

        <div class="users-grid" id="usersTable">
            <?php foreach ($users as $user): ?>
                <div class="user-profile-card <?= $user['ban'] ? 'user-profile-card--banned' : '' ?>">
                    <div class="user-profile-card__content" role="button" tabindex="0" data-user-id="<?= (int) $user['id'] ?>" data-search>
                        <img src="img/pdps/<?= htmlspecialchars($user['pdp']) ?>"
                             alt="" class="user-profile-card__avatar" width="64" height="64" loading="lazy" decoding="async">
                        <div class="user-profile-card__info">
                            <div class="user-profile-card__name">
                                <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>
                                <?php if ((int) $user['id'] === (int) $currentId): ?>
                                    <span class="user-profile-card__you">(toi)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="user-profile-menu" data-menu-for="<?= (int) $user['id'] ?>">
                        <div class="user-profile-menu__header">
                            <h4><?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?></h4>
                            <p class="text-white"><?= htmlspecialchars($user['email']) ?></p>
                            <div class="user-profile-menu__badges mt-2">
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
                        </div>

                        <div class="user-profile-menu__actions">
                            <button type="button" class="btn btn-sm w-100 btn-warning user-action-btn" 
                                    data-action="toggle_gerer_seances" data-user-id="<?= (int) $user['id'] ?>"
                                    title="<?= $user['gerer_seances'] ? 'Retirer la gestion des séances' : 'Autoriser la gestion des séances' ?>"
                                    <?= (int) $user['id'] === (int) $currentId ? 'disabled' : '' ?>>
                                <i class="bi bi-calendar2-check me-2"></i>
                                <span class="btn-text"><?= $user['gerer_seances'] ? 'Retirer séances' : 'Gérer séances' ?></span>
                            </button>

                            <button type="button" class="btn btn-sm w-100 btn-danger user-action-btn" 
                                    data-action="toggle_ban" data-user-id="<?= (int) $user['id'] ?>"
                                    title="<?= $user['ban'] ? 'Débannir' : 'Bannir' ?>"
                                    <?= (int) $user['id'] === (int) $currentId ? 'disabled' : '' ?>>
                                <i class="bi <?= $user['ban'] ? 'bi-person-check' : 'bi-person-slash' ?> me-2"></i>
                                <span class="btn-text"><?= $user['ban'] ? 'Débannir' : 'Bannir' ?></span>
                            </button>

                            <button type="button" class="btn btn-sm w-100 btn-info user-action-btn" 
                                    data-action="verify_email" data-user-id="<?= (int) $user['id'] ?>"
                                    title="Vérifier l'email"
                                    <?= ((int) $user['id'] === (int) $currentId || (int) $user['email_verified'] === 1) ? 'disabled' : '' ?>>
                                <i class="bi bi-check-circle me-2"></i>
                                Vérifier le mail
                            </button>

                            <button type="button" class="btn btn-sm w-100 btn-danger user-action-btn" 
                                    data-action="delete_account" data-user-id="<?= (int) $user['id'] ?>"
                                    title="Supprimer le compte"
                                    <?= (int) $user['id'] === (int) $currentId ? 'disabled' : '' ?>>
                                <i class="bi bi-trash me-2"></i>
                                Supprimer le compte
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($users)): ?>
                <p class="upcoming-empty text-center py-4 w-100">Aucun utilisateur enregistré.</p>
            <?php endif; ?>
        </div>

    </div>
</section>

<?php require 'includes/general/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Search functionality
        document.getElementById('userSearch').addEventListener('input', function (e) {
            const query = e.target.value.trim().toLowerCase();
            document.querySelectorAll('#usersTable .user-profile-card').forEach(function (card) {
                const text = card.querySelector('[data-search]')?.textContent.toLowerCase() || '';
                card.style.display = text.includes(query) ? '' : 'none';
            });
        });

        // Menu toggle functionality
        document.querySelectorAll('.user-profile-card__content').forEach(function(element) {
            element.addEventListener('click', function(e) {
                e.stopPropagation();
                const userId = this.getAttribute('data-user-id');
                const card = this.closest('.user-profile-card');
                const menu = card ? card.querySelector('.user-profile-menu') : document.querySelector(`[data-menu-for="${userId}"]`);
                const isOpen = menu?.classList.contains('active');
                
                document.querySelectorAll('.user-profile-menu').forEach(function(m) {
                    m.classList.remove('active');
                });
                document.querySelectorAll('.user-profile-card').forEach(function(c) {
                    c.classList.remove('is-expanded');
                });
                
                if (menu && !isOpen) {
                    menu.classList.add('active');
                    card?.classList.add('is-expanded');
                }
            });

            element.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });

        // Close menu when clicking outside
        document.addEventListener('click', function() {
            document.querySelectorAll('.user-profile-menu').forEach(function(menu) {
                menu.classList.remove('active');
            });
            document.querySelectorAll('.user-profile-card').forEach(function(card) {
                card.classList.remove('is-expanded');
            });
        });

        // Prevent menu from closing when clicking inside it
        document.querySelectorAll('.user-profile-menu').forEach(function(menu) {
            menu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });

        // User action buttons (AJAX)
        document.querySelectorAll('.user-action-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const action = this.getAttribute('data-action');
                const userId = parseInt(this.getAttribute('data-user-id'));
                const button = this;

                // Confirmation for delete
                if (action === 'delete_account') {
                    if (!confirm('Êtes-vous sûr de vouloir supprimer ce compte ? Cette action est irréversible.')) {
                        return;
                    }
                }

                // Show loading state
                const originalText = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';

                fetch('includes/general/users-api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: action,
                        target_id: userId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Restore original HTML first
                        button.innerHTML = originalText;
                        button.disabled = false;

                        // Handle specific actions
                        if (action === 'toggle_gerer_seances') {
                            const btnText = button.querySelector('.btn-text');
                            
                            if (btnText) {
                                // Update button appearance
                                if (data.new_value === 1) {
                                    button.classList.remove('btn-outline-warning');
                                    button.classList.add('btn-warning');
                                    btnText.textContent = 'Retirer séances';
                                } else {
                                    button.classList.add('btn-outline-warning');
                                    button.classList.remove('btn-warning');
                                    btnText.textContent = 'Gérer séances';
                                }
                            }
                        } else if (action === 'toggle_ban') {
                            const card = document.querySelector(`[data-menu-for="${userId}"]`);
                            const userCard = card ? card.closest('.user-profile-card') : null;
                            const btnText = button.querySelector('.btn-text');
                            const btnIcon = button.querySelector('.bi');
                            
                            if (btnText && btnIcon && userCard) {
                                // Update card visual state
                                if (data.new_value === 1) {
                                    userCard.classList.add('user-profile-card--banned');
                                    button.classList.add('btn-success');
                                    button.classList.remove('btn-danger');
                                    btnText.textContent = 'Débannir';
                                    btnIcon.className = 'bi bi-person-check me-2';
                                } else {
                                    userCard.classList.remove('user-profile-card--banned');
                                    button.classList.remove('btn-success');
                                    button.classList.add('btn-danger');
                                    btnText.textContent = 'Bannir';
                                    btnIcon.className = 'bi bi-person-slash me-2';
                                }
                            }
                        } else if (action === 'verify_email') {
                            // Disable the button after verification
                            button.disabled = true;
                            button.innerHTML = '<i class="bi bi-check-circle me-2"></i>Email vérifié';
                        } else if (action === 'delete_account') {
                            // Remove the card from the DOM
                            const card = document.querySelector(`[data-menu-for="${userId}"]`);
                            const userCard = card ? card.closest('.user-profile-card') : null;
                            if (userCard) {
                                userCard.remove();
                            }
                        }

                        showNotification(data.message, 'success');
                    } else {
                        button.innerHTML = originalText;
                        button.disabled = false;
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    button.innerHTML = originalText;
                    button.disabled = false;
                    showNotification('Une erreur est survenue', 'error');
                });
            });
        });

        // Simple notification function
        function showNotification(message, type) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const alertHtml = `<div class="alert ${alertClass} alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;
            
            const alertContainer = document.createElement('div');
            alertContainer.innerHTML = alertHtml;
            const alertEl = alertContainer.firstElementChild;
            document.body.appendChild(alertEl);

            // Auto-dismiss after 3 seconds
            setTimeout(() => {
                alertEl.classList.remove('show');
                setTimeout(() => {
                    alertEl.remove();
                }, 150);
            }, 3000);
        }
    });
</script>

</body>
</html>

