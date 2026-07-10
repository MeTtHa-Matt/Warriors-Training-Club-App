<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";
require 'includes/general/db.php';
require 'includes/general/mailer.php';

include __DIR__ . '/includes/general/mdp_oublie.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607102000">
    <link rel="manifest" href="./manifest.json">
    <link rel="icon" type="image/png" sizes="any" href="./img/wtc.png">
    <link rel="apple-touch-icon" sizes="180x180" href="./img/wtc.png">
    <meta name="application-name" content="Warriors Training Club">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Warriors">
    <meta name="msapplication-TileColor" content="#0C0B0A">
    <meta name="msapplication-TileImage" content="./img/wtc.png">
    <meta name="theme-color" content="#C9A227">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="icon" type="image/png" href="./img/wtc.png">
</head>

<body>
    <?php require 'includes/general/navbar.php'; ?>

    <section class="section auth-section">
        <div class="container">
            <div class="auth-wrapper">
                <div class="section-head text-center text-lg-start mx-auto mx-lg-0">
                    <p class="eyebrow">Espace membre</p>
                    <h2>Mot de passe oublié</h2>
                    <p class="auth-hint mt-2">Saisis ton adresse email pour recevoir un lien de réinitialisation.</p>
                </div>

                <?php if ($success): ?>
                    <div class="auth-alert auth-alert--success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="auth-alert auth-alert--error">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" class="auth-form" novalidate>
                    <div class="mb-3">
                        <label for="email" class="form-label">Adresse email</label>
                        <input type="email" class="form-control auth-input" id="email" name="email" required
                            maxlength="255">
                    </div>

                    <button type="submit" class="btn btn-wtc-gold rounded-pill w-100 mt-4">Envoyer le lien</button>

                    <p class="auth-switch mt-4">
                        <a href="connexion.php">Retour à la connexion</a>
                    </p>
                </form>
            </div>
        </div>
    </section>

    <?php require 'includes/general/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
