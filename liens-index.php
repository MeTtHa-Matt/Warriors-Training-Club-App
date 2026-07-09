<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";
require_once "includes/general/db.php";

include __DIR__ . '/includes/general/modifier-liens-index.php'
    ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                    <h1 class="mt-3 mb-3">Modifier les <span class="accent">liens</span> de la page d'accueil</h1>
                    <p class="lead">Mets à jour les URLs des boutons externes affichés sur la page d'accueil.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section section--compact">
        <div class="container mb-4">
            <a href="administration.php" class="btn btn-wtc-outline rounded-pill">
                <i class="bi bi-arrow-left me-2"></i>Retour à l'administration
            </a>
        </div>
    </section>

    <section class="section" id="liens-index-admin">
        <div class="container">
            <div class="auth-wrapper">
                <div class="section-head text-start mb-4">
                    <p class="eyebrow">Administration</p>
                    <h2>Gérer les liens d'accueil</h2>
                    <p class="lead mb-0">Mets à jour les liens des boutons et cartes affichés sur la page d'accueil.</p>
                </div>

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

                <form method="POST" class="auth-form">
                    <?php foreach ($defaultLinks as $key => $link): ?>
                        <div class="mb-4">
                            <label for="<?= htmlspecialchars($key) ?>" class="form-label">URL du lien «
                                <?= htmlspecialchars($link['title']) ?> »</label>
                            <input id="<?= htmlspecialchars($key) ?>" name="<?= htmlspecialchars($key) ?>" type="url"
                                class="form-control auth-input" value="<?= htmlspecialchars($link['url']) ?>"
                                placeholder="https://example.com" required>
                            <p class="auth-hint">Bouton / carte : <?= htmlspecialchars($link['label']) ?> —
                                <?= htmlspecialchars($link['title']) ?>.</p>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-wtc-gold rounded-pill px-4 w-100 w-md-auto">Enregistrer le
                        lien</button>
                </form>
            </div>
        </div>
    </section>

    <?php require 'includes/general/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>