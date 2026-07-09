<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";

require 'includes/general/db.php';
require 'includes/general/mailer.php';

include __DIR__ . '/includes/general/envoyer-mail.php'
    ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Warriors Training Club - Envoyer un mail</title>
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
                    <h1 class="mt-3 mb-3">Envoyer un <span class="accent">mail</span></h1>
                    <p class="lead">Rédige un message clair et personnalisé pour l’ensemble des membres du club.</p>
                </div>
            </div>
        </div>
    </section>

    <div class="section section--compact">
        <div class="container mx-auto">
            <a href="administration.php" class="btn btn-wtc-outline rounded-pill">
                <i class="bi bi-arrow-left me-2"></i>Retour à l'administration
            </a>
        </div>
    </div>

    <section class="section" id="envoyer-mail">
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

            <div class="mail-shell">
                <div class="col-12 col-lg-7">
                    <div class="mail-card">
                        <h2 class="mail-card__title">Écrire un message à tous les utilisateurs</h2>

                        <form method="POST" class="mail-form" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="subject" class="form-label">Objet du mail</label>
                                    <input type="text" class="form-control auth-input" id="subject" name="subject"
                                        placeholder="Ex. : Informations importantes sur la semaine"
                                        value="<?= htmlspecialchars($postedSubject) ?>" required>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Mise en forme</label>
                                    <div class="mail-toolbar">
                                        <button type="button" class="mail-toolbar__btn" data-command="bold"><i
                                                class="bi bi-type-bold"></i></button>
                                        <button type="button" class="mail-toolbar__btn" data-command="italic"><i
                                                class="bi bi-type-italic"></i></button>
                                        <button type="button" class="mail-toolbar__btn" data-command="underline"><i
                                                class="bi bi-type-underline"></i></button>
                                        <select class="mail-toolbar__select" id="fontSizeSelect"
                                            aria-label="Taille de police">
                                            <option value="">Taille</option>
                                            <option value="1">Petite</option>
                                            <option value="3">Normale</option>
                                            <option value="4">Grande</option>
                                            <option value="5">Très grande</option>
                                            <option value="6">Énorme</option>
                                        </select>
                                        <input type="color" class="mail-toolbar__color" id="fontColorInput"
                                            aria-label="Couleur de police" value="#f4efe2">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label for="messageEditor" class="form-label">Message</label>
                                    <div class="mail-editor" id="messageEditor" contenteditable="true"></div>
                                    <textarea id="message_html" name="message_html" hidden></textarea>
                                </div>

                                <div class="col-12">
                                    <label for="attachments" class="form-label">Pièces jointes</label>
                                    <input type="file" class="form-control auth-input" id="attachments"
                                        name="attachments[]" multiple
                                        accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
                                    <div class="mail-attachment-list" id="attachmentFiles"></div>
                                </div>

                                <div class="col-12">
                                    <label for="signature" class="form-label">Signature</label>
                                    <input type="text" class="form-control auth-input" id="signature" name="signature"
                                        placeholder="Ex. : L’équipe du club"
                                        value="<?= htmlspecialchars($postedSignature) ?>">
                                </div>
                            </div>

                            <div class="mail-actions mt-4">
                                <button type="submit" class="btn btn-wtc-gold rounded-pill px-4">
                                    <i class="bi bi-send me-2"></i>Envoyer le mail
                                </button>
                                <span class="text-muted small">À <?= $recipientCount ?>
                                    destinataire<?= $recipientCount > 1 ? 's' : '' ?></span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php require 'includes/general/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/envoyer-mail.js"></script>

</body>

</html>