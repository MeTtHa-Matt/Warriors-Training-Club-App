<?php
require_once 'includes/general/administration.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607051">
    <link rel="icon" type="image/png" href="img/wtc.png">
</head>

<body>

    <?php require 'includes/general/navbar.php'; ?>

    <section class="hero hero--compact">
        <div class="container">
            <div class="row">
                <div class="col-12 col-lg-8">
                    <span class="hero-badge mb-3"><span class="dot"></span>Administration</span>
                    <h1 class="mt-3 mb-3">Tableau de bord <span class="accent">administrateur</span></h1>
                    <p class="lead">Accède à toutes les actions réservées aux administrateurs et consulte les
                        informations clés du site.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="administration-dashboard">
        <div class="container">
            <?php if (!empty($errors)): ?>
                <div class="auth-alert auth-alert--error mb-4">
                    <?php foreach ($errors as $error): ?>
                        <p class="mb-1"><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="auth-alert auth-alert--success mb-4">
                    <p class="mb-0"><?= htmlspecialchars($success) ?></p>
                </div>
            <?php endif; ?>

            <div class="section-head mb-4">
                <p class="eyebrow">Actions administratives</p>
                <h2>Menu administration</h2>
            </div>

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                <?php foreach ($adminActions as $action): ?>
                    <div class="col">
                        <a href="<?= htmlspecialchars($action['url']) ?>"
                            class="admin-action-card d-block p-4 h-100 text-decoration-none">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <i class="<?= htmlspecialchars($action['icon']) ?> fs-2"></i>
                                <div>
                                    <h3 class="h5 mb-1 text-white">
                                        <?= htmlspecialchars($action['label']) ?>
                                    </h3>
                                    <p class="text-white mb-0">
                                        <?= htmlspecialchars($action['description']) ?>
                                    </p>
                                </div>
                            </div>
                            <span class="badge bg-wtc-gold text-white">Accéder</span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-5">
                <div class="col">
                    <div class="info-card p-4 h-100">
                        <div class="info-card__title">Comptes totaux</div>
                        <div class="info-card__value display-6"><?= number_format($totalUsers, 0, ',', ' ') ?></div>
                        <p class="mb-0 text-white">Dont <?= number_format($totalAdmins, 0, ',', ' ') ?> administrateurs.
                        </p>
                    </div>
                </div>
                <div class="col">
                    <div class="info-card p-4 h-100">
                        <div class="info-card__title">Sessions enregistrées</div>
                        <div class="info-card__value display-6"><?= number_format($totalSessions, 0, ',', ' ') ?></div>
                        <p class="mb-0 text-white">Dont <?= number_format($upcomingSessions, 0, ',', ' ') ?> à venir.
                        </p>
                    </div>
                </div>
                <div class="col">
                    <div class="info-card p-4 h-100">
                        <div class="info-card__title">Inscriptions</div>
                        <div class="info-card__value display-6"><?= number_format($totalInscriptions, 0, ',', ' ') ?>
                        </div>
                        <p class="mb-0 text-white">Total des inscriptions aux séances.</p>
                    </div>
                </div>
                <div class="col">
                    <div class="info-card p-4 h-100">
                        <div class="info-card__title">Signalements</div>
                        <div class="info-card__value display-6"><?= number_format($totalReports, 0, ',', ' ') ?></div>
                        <p class="mb-0 text-white">Problèmes signalés par les membres.</p>
                    </div>
                </div>
                <div class="col">
                    <div class="info-card p-4 h-100">
                        <div class="info-card__title">Mails non acceptés</div>
                        <div class="info-card__value display-6"><?= number_format($totalEmailsOptOut, 0, ',', ' ') ?>
                        </div>
                        <p class="mb-0 text-white">Utilisateurs ayant refusé les emails club.</p>
                    </div>
                </div>
                <div class="col">
                    <div class="info-card p-4 h-100">
                        <div class="info-card__title">Maintenance active</div>
                        <div class="info-card__value display-6">
                            <?= number_format($totalMaintenanceUsers, 0, ',', ' ') ?></div>
                        <p class="mb-0 text-white">Comptes signalant le mode maintenance.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php require 'includes/general/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>