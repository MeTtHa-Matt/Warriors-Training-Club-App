<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";

$pageTitle = "Warriors Training Club - Inscription";

$errors = $_SESSION['errors'] ?? [];
$old = $_SESSION['old'] ?? [];
unset($_SESSION['errors'], $_SESSION['old']);
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
                    <p class="eyebrow">Rejoindre le club</p>
                    <h2>Créer mon compte</h2>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="auth-alert auth-alert--error">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="includes/account/inscription_process.php" method="post" enctype="multipart/form-data"
                    class="auth-form" novalidate>

                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label for="firstname" class="form-label">Prénom</label>
                            <input type="text" class="form-control auth-input" id="firstname" name="firstname"
                                value="<?php echo htmlspecialchars($old['firstname'] ?? ''); ?>" required
                                maxlength="100">
                        </div>

                        <div class="col-12 col-sm-6">
                            <label for="lastname" class="form-label">Nom</label>
                            <input type="text" class="form-control auth-input" id="lastname" name="lastname"
                                value="<?php echo htmlspecialchars($old['lastname'] ?? ''); ?>" required
                                maxlength="150">
                        </div>

                        <div class="col-12">
                            <label for="email" class="form-label">Adresse email</label>
                            <input type="email" class="form-control auth-input" id="email" name="email"
                                value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" required maxlength="255">
                        </div>

                        <div class="col-12 col-sm-6">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control auth-input" id="password" name="password"
                                required minlength="8">
                        </div>

                        <div class="col-12 col-sm-6">
                            <label for="password_confirm" class="form-label">Confirmer</label>
                            <input type="password" class="form-control auth-input" id="password_confirm"
                                name="password_confirm" required minlength="8">
                        </div>

                        <div class="col-12">
                            <label for="pdp" class="form-label">Photo de profil <span
                                    class="auth-optional">(facultatif)</span></label>
                            <input type="file" class="form-control auth-input" id="pdp" name="pdp"
                                accept="image/png, image/jpeg, image/webp">
                            <p class="auth-hint">JPG, PNG ou WEBP — 5 Mo maximum.</p>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-wtc-gold rounded-pill w-100 mt-4">S'inscrire</button>

                    <p class="auth-switch">Déjà un compte ? <a href="connexion.php">Se connecter</a></p>
                </form>

            </div>
        </div>
    </section>

    <?php require 'includes/general/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>