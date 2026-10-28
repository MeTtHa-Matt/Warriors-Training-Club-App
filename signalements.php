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
    <meta http-equiv="Cache-Control" content="no-store">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202610281033">
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
                <p class="lead"><?= $userId > 0 ? 'Décris le problème rencontré. Ton signalement sera envoyé directement.' : 'Décris le problème rencontré. Ton adresse e-mail sera vérifiée avant l’envoi.' ?></p>
            </div>
        </div>
    </div>
</section>

<section class="section" id="signalements">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
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

                <?php if ($step === 'message' && !$activeReport): ?>
                    <div class="auth-wrapper">
                        <form class="auth-form" method="POST" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['report_csrf']) ?>">
                            <div class="mb-3">
                                <label for="report_message" class="form-label">Description du problème</label>
                                <textarea class="form-control auth-input" id="report_message" name="report_message" rows="7" maxlength="4000" required><?= htmlspecialchars($reportData['message'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" name="prepare_report" class="btn btn-wtc-gold rounded-pill w-100">
                                Envoyer le signalement
                            </button>
                        </form>
                    </div>
                <?php elseif ($step === 'email' && !$activeReport): ?>
                    <div class="auth-wrapper">
                        <form class="auth-form" method="POST" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['report_csrf']) ?>">
                            <label for="report_email" class="form-label">Ton adresse e-mail</label>
                            <input type="email" class="form-control auth-input mb-3" id="report_email" name="email" maxlength="254" autocomplete="email" required>
                            <p class="auth-hint">Un code de confirmation valable 10 minutes sera envoyé à cette adresse.</p>
                            <button type="submit" name="send_code" class="btn btn-wtc-gold rounded-pill w-100">Recevoir le code</button>
                        </form>
                    </div>
                <?php elseif ($step === 'code' && !$activeReport): ?>
                    <div class="auth-wrapper">
                        <form class="auth-form" method="POST" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['report_csrf']) ?>">
                            <label for="verification_code" class="form-label">Code reçu par e-mail</label>
                            <input type="text" class="form-control auth-input mb-3" id="verification_code" name="verification_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                            <button type="submit" name="verify_report" class="btn btn-wtc-gold rounded-pill w-100">Envoyer le signalement</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($userReports): ?>
                    <div class="report-list">
                        <h2 class="h4 mb-3">Mes dossiers</h2>
                        <?php foreach ($userReports as $report): ?>
                            <a class="report-list__item" href="signalements.php?report=<?= rawurlencode($report['id']) ?>">
                                <span class="report-list__icon"><i class="bi bi-chat-dots"></i></span>
                                <span class="report-list__content">
                                    <strong><?= htmlspecialchars(mb_strimwidth($report['message'], 0, 100, '…')) ?></strong>
                                    <small>Ouvert le <?= htmlspecialchars(date('d/m/Y à H:i', strtotime($report['created_at']))) ?> · <?= $report['status'] === 'open' ? 'En cours' : 'Résolu' ?></small>
                                </span>
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($activeReport): ?>
<section class="report-conversation report-conversation--active" aria-label="Conversation du signalement"
    data-report-conversation data-report-id="<?= htmlspecialchars($activeReport['id']) ?>"
    data-endpoint="api/report-conversation.php" data-csrf="<?= htmlspecialchars($_SESSION['report_csrf']) ?>"
    data-viewer-role="user" data-last-message-id="<?= $messages ? (int) end($messages)['id'] : 0 ?>">
    <header class="report-conversation__header">
        <a class="report-conversation__back" href="signalements.php" aria-label="Retour aux signalements"><i class="bi bi-arrow-left"></i></a>
        <span class="report-conversation__avatar"><i class="bi bi-shield-check"></i></span>
        <div class="report-conversation__identity">
            <strong>Assistance WTC</strong>
            <small><?= $activeReport['status'] === 'open' ? 'En conversation' : 'Dossier résolu' ?></small>
        </div>
        <span class="report-status <?= $activeReport['status'] === 'open' ? 'report-status--open' : 'report-status--closed' ?>">
            <?= $activeReport['status'] === 'open' ? 'En cours' : 'Résolu' ?>
        </span>
    </header>
    <div class="report-conversation__body">
        <div class="report-chat-date"><?= htmlspecialchars(date('d/m/Y à H:i', strtotime($activeReport['created_at']))) ?></div>
        <article class="report-chat-message report-chat-message--user">
            <div class="report-chat-message__bubble"><?= nl2br(htmlspecialchars($activeReport['message'])) ?></div>
            <time><?= htmlspecialchars(date('H:i', strtotime($activeReport['created_at']))) ?></time>
        </article>
        <?php foreach ($messages as $message): ?>
            <article class="report-chat-message <?= $message['sender_type'] === 'admin' ? 'report-chat-message--admin' : 'report-chat-message--user' ?>">
                <div class="report-chat-message__bubble">
                    <?php if ($message['sender_type'] === 'admin'): ?><small class="report-chat-message__sender">Assistance WTC</small><?php endif; ?>
                    <?= nl2br(htmlspecialchars($message['message'])) ?>
                </div>
                <time><?= htmlspecialchars(date('H:i · d/m/Y', strtotime($message['created_at']))) ?></time>
            </article>
        <?php endforeach; ?>
    </div>
    <p class="report-conversation__error" data-conversation-error role="alert" hidden></p>
    <?php if ($activeReport['status'] === 'open'): ?>
        <form class="report-conversation__composer" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['report_csrf']) ?>">
            <input type="hidden" name="report_id" value="<?= htmlspecialchars($activeReport['id']) ?>">
            <label class="visually-hidden" for="reply_message">Écrire un message</label>
            <textarea id="reply_message" name="reply_message" rows="1" maxlength="4000" placeholder="Écrire un message…" required></textarea>
            <button type="submit" name="send_reply" aria-label="Envoyer"><i class="bi bi-send-fill"></i></button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php require 'includes/general/footer.php'; ?>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/report-conversation.js?v=202610281045"></script>
</body>
</html>
