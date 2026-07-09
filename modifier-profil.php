<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";
require 'includes/general/db.php';

include __DIR__ . '/includes/general/modifier-profil.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warriors Training Club - Modifier mon profil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= htmlspecialchars($assetVersion) ?>">
    <link rel="icon" type="image/png" href="img/wtc.png">
</head>

<body>

    <?php require 'includes/general/navbar.php'; ?>

    <section class="hero hero--compact">
        <div class="container">
            <div class="row">
                <div class="col-12 col-lg-8">
                    <span class="hero-badge mb-3"><span class="dot"></span>Mon compte</span>
                    <h1 class="mt-3 mb-3">Modifier mon <span class="accent">profil</span></h1>
                    <p class="lead">Mets à jour tes informations personnelles, ta photo et ton mot de passe.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="profil">
        <div class="container">

            <div class="row g-4 justify-content-center">

                <!-- Colonne : infos personnelles + photo -->
                <div class="col-12 col-lg-6">
                    <div class="auth-wrapper">
                        <p class="eyebrow mb-3">Informations personnelles</p>

                        <?php if (!empty($errors)): ?>
                            <div class="auth-alert auth-alert--error">
                                <?php foreach ($errors as $error): ?>
                                    <p class="mb-1">
                                        <?= htmlspecialchars($error) ?>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="auth-alert auth-alert--success">
                                <p class="mb-0">
                                    <?= htmlspecialchars($success) ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <form class="auth-form" method="POST" enctype="multipart/form-data" novalidate>
                            <div class="text-center mb-4">
                                <img src="img/pdps/<?= htmlspecialchars($account['pdp']) ?>" alt="Photo de profil"
                                    class="profil-avatar mb-3">
                                <div>
                                    <label for="pdp" class="form-label d-block">Photo de profil <span
                                            class="auth-optional">(facultatif)</span></label>
                                    <input type="file" class="form-control auth-input" id="pdp" name="pdp"
                                        accept="image/jpeg, image/png, image/webp">
                                    <p class="auth-hint">JPEG, PNG ou WebP — 2 Mo maximum.</p>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="firstname" class="form-label">Prénom</label>
                                <input type="text" class="form-control auth-input" id="firstname" name="firstname"
                                    value="<?= htmlspecialchars($account['firstname']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="lastname" class="form-label">Nom</label>
                                <input type="text" class="form-control auth-input" id="lastname" name="lastname"
                                    value="<?= htmlspecialchars($account['lastname']) ?>" required>
                            </div>

                            <div class="mb-4 form-check form-switch">
                                <input type="checkbox" class="form-check-input" role="switch" id="accept_email"
                                    name="accept_email" <?= $account['accept_email'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="accept_email">
                                    Recevoir les emails d'information du club
                                </label>
                            </div>

                            <button type="submit" name="update_infos" class="btn btn-wtc-gold rounded-pill w-100">
                                Enregistrer les modifications
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Colonne : mot de passe -->
                <div class="col-12 col-lg-6">
                    <div class="auth-wrapper">
                        <p class="eyebrow mb-3">Sécurité</p>

                        <?php if (!empty($passwordErrors)): ?>
                            <div class="auth-alert auth-alert--error">
                                <?php foreach ($passwordErrors as $error): ?>
                                    <p class="mb-1">
                                        <?= htmlspecialchars($error) ?>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($passwordSuccess): ?>
                            <div class="auth-alert auth-alert--success">
                                <p class="mb-0">
                                    <?= htmlspecialchars($passwordSuccess) ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <form class="auth-form" method="POST" novalidate>
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Mot de passe actuel</label>
                                <input type="password" class="form-control auth-input" id="current_password"
                                    name="current_password" required>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control auth-input" id="new_password"
                                    name="new_password" required minlength="8">
                                <p class="auth-hint">8 caractères minimum.</p>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirmer le nouveau mot de
                                    passe</label>
                                <input type="password" class="form-control auth-input" id="confirm_password"
                                    name="confirm_password" required minlength="8">
                            </div>

                            <button type="submit" name="update_password" class="btn btn-wtc-outline rounded-pill w-100">
                                Modifier le mot de passe
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Colonne : suppression du compte -->
                <div class="col-12 col-lg-6">
                    <div class="auth-wrapper auth-wrapper--danger">
                        <p class="eyebrow mb-3 text-danger">Zone dangereuse</p>

                        <?php if (!empty($deleteErrors)): ?>
                            <div class="auth-alert auth-alert--error">
                                <?php foreach ($deleteErrors as $error): ?>
                                    <p class="mb-1">
                                        <?= htmlspecialchars($error) ?>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <p class="auth-hint mb-3">
                            La suppression de ton compte est <strong>définitive</strong> et irréversible.
                            Toutes tes informations personnelles et ta photo de profil seront supprimées.
                        </p>

                        <form class="auth-form" method="POST" novalidate
                            onsubmit="return confirm('Es-tu sûr(e) de vouloir supprimer définitivement ton compte ? Cette action est irréversible.');">
                            <div class="mb-4">
                                <label for="delete_password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control auth-input" id="delete_password"
                                    name="delete_password" required>
                                <p class="auth-hint">Confirme ton mot de passe pour supprimer définitivement ton compte.
                                </p>
                            </div>

                            <button type="submit" name="delete_account"
                                class="btn btn-outline-danger rounded-pill w-100">
                                <i class="bi bi-trash3 me-1"></i> Supprimer mon compte
                            </button>
                        </form>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <?php require 'includes/general/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/accept-email-toggle.js"></script>

</body>

</html>