<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";
require 'includes/general/db.php';
require 'includes/general/mailer.php';

include __DIR__ . '/includes/general/signalements.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607102000">
    <link rel="icon" type="image/png" href="img/wtc.png">
</head>
<body>

<?php require 'includes/general/navbar.php'; ?>

<section class="hero hero--compact">
    <div class="container">
        <div class="row">
            <div class="col-12 col-lg-8">
                <span class="hero-badge mb-3"><span class="dot"></span>Mon compte</span>
                <h1 class="mt-3 mb-3">Signaler un <span class="accent">problème</span></h1>
                <p class="lead">Envoie un signalement si tu rencontres un souci sur le site ou dans l’organisation d’une séance.</p>
            </div>
        </div>
    </div>
</section>

<section class="section" id="signalements">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <div class="auth-wrapper">
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

                    <p class="auth-hint mb-3">
                        Tu peux envoyer jusqu'à 3 signalements par semaine. Tu as déjà envoyé <?= (int) $reportCountThisWeek ?> signalement(s) cette semaine.
                    </p>

                    <form class="auth-form" method="POST" novalidate>
                        <div class="mb-3">
                            <label for="report_subject" class="form-label">Sujet</label>
                            <input type="text" class="form-control auth-input" id="report_subject" name="report_subject" maxlength="150" required>
                        </div>

                        <div class="mb-3">
                            <label for="report_message" class="form-label">Description du problème</label>
                            <textarea class="form-control auth-input" id="report_message" name="report_message" rows="6" maxlength="4000" required></textarea>
                        </div>

                        <button type="submit" name="submit_report" class="btn btn-wtc-outline rounded-pill w-100">
                            Envoyer le signalement
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require 'includes/general/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

