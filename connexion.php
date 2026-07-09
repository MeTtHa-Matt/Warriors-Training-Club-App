<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";

$pageTitle = "Warriors Training Club - Connexion";

$errors = $_SESSION['errors'] ?? [];
$old = $_SESSION['old'] ?? [];
$success = $_SESSION['success'] ?? null;
unset($_SESSION['errors'], $_SESSION['old'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607051">
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
</head>

<body>

    <?php require 'includes/general/navbar.php'; ?>

    <section class="section auth-section">
        <div class="container">
            <div class="auth-wrapper">

                <div class="section-head text-center text-lg-start mx-auto mx-lg-0">
                    <p class="eyebrow">Espace membre</p>
                    <h2>Me connecter</h2>
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

                <form action="includes/account/connexion_process.php" method="post" class="auth-form" novalidate>

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="email" class="form-label">Adresse email</label>
                            <input type="email" class="form-control auth-input" id="email" name="email"
                                value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" required maxlength="255">
                        </div>

                        <div class="col-12">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control auth-input" id="password" name="password"
                                required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-wtc-gold rounded-pill w-100 mt-4">Se connecter</button>

                    <p class="auth-link mt-3">
                        <a href="mot-de-passe-oublie.php">Mot de passe oublié ?</a>
                    </p>

                    <p class="auth-switch">Pas encore de compte ? <a href="inscription.php">S'inscrire</a></p>
                </form>

            </div>
        </div>
    </section>

    <?php require 'includes/general/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>