<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";
require 'includes/general/mailer.php';

include __DIR__ . '/includes/general/signalements.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607102000">
    <link rel="icon" type="image/png" href="img/wtc.png">
</head>
<body>

<?php require 'includes/general/navbar.php'; ?>

<section class="hero hero--compact">
    <div class="container">
        <div class="row">
            <div class="col-12 col-lg-8">
                <span class="hero-badge mb-3"><span class="dot"></span>Assistance</span>
                <h1 class="mt-3 mb-3">Signaler un <span class="accent">problème</span></h1>
                <p class="lead">Décris le problème rencontré. Ton adresse e-mail sera vérifiée avant l’envoi.</p>
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

                    <?php if ($step === 'message'): ?>
                    <form class="auth-form" method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['report_csrf']) ?>">
                        <div class="mb-3">
                            <label for="report_message" class="form-label">Description du problème</label>
                            <textarea class="form-control auth-input" id="report_message" name="report_message" rows="7" maxlength="4000" required><?= htmlspecialchars($reportData['message'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" name="prepare_report" class="btn btn-wtc-gold rounded-pill w-100">
                            Continuer
                        </button>
                    </form>
                    <?php elseif ($step === 'email'): ?>
                    <form class="auth-form" method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['report_csrf']) ?>">
                        <label for="report_email" class="form-label">Ton adresse e-mail</label>
                        <input type="email" class="form-control auth-input mb-3" id="report_email" name="email" maxlength="254" autocomplete="email" required>
                        <p class="auth-hint">Un code de confirmation valable 10 minutes sera envoyé à cette adresse.</p>
                        <button type="submit" name="send_code" class="btn btn-wtc-gold rounded-pill w-100">Recevoir le code</button>
                    </form>
                    <?php else: ?>
                    <form class="auth-form" method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['report_csrf']) ?>">
                        <label for="verification_code" class="form-label">Code reçu par e-mail</label>
                        <input type="text" class="form-control auth-input mb-3" id="verification_code" name="verification_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                        <button type="submit" name="verify_report" class="btn btn-wtc-gold rounded-pill w-100">Envoyer le signalement</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require 'includes/general/footer.php'; ?>

<script src="js/bootstrap.bundle.min.js"></script>

</body>
</html>

