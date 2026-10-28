<?php
require_once 'includes/general/reports-admin.php';
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
        <span class="hero-badge mb-3"><span class="dot"></span>Administration</span>
        <h1 class="mt-3 mb-3">Boîte des <span class="accent">signalements</span></h1>
        <p class="lead">Réponds aux dossiers et ferme-les une fois résolus.</p>
    </div>
</section>
<section class="section">
    <div class="container">
        <?php if ($success): ?>
            <div class="auth-alert auth-alert--success mb-4"><p class="mb-0"><?= htmlspecialchars($success) ?></p></div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="auth-alert auth-alert--error mb-4">
                <?php foreach ($errors as $error): ?><p class="mb-1"><?= htmlspecialchars($error) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (empty($reports)): ?>
            <div class="admin-empty-state"><i class="bi bi-inbox"></i><p class="mb-0">Aucun dossier en cours.</p></div>
        <?php else: ?>
            <div class="report-list report-list--admin">
                <h2 class="h4 mb-3">Dossiers en cours <span class="report-list__count"><?= count($reports) ?></span></h2>
                <?php foreach ($reports as $report): ?>
                    <a class="report-list__item" href="reports.php?report=<?= rawurlencode($report['id']) ?>">
                        <span class="report-list__icon"><i class="bi bi-chat-dots"></i></span>
                        <span class="report-list__content">
                            <strong><?= htmlspecialchars($report['email']) ?></strong>
                            <small><?= htmlspecialchars(mb_strimwidth($report['latest_message'] ?: $report['message'], 0, 110, '…')) ?></small>
                        </span>
                        <time><?= htmlspecialchars(date('d/m/Y à H:i', strtotime($report['latest_message_at'] ?: $report['created_at']))) ?></time>
                        <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($activeReport): ?>
<section class="report-conversation report-conversation--active" aria-label="Conversation du signalement"
    data-report-conversation data-report-id="<?= htmlspecialchars($activeReport['id']) ?>"
    data-endpoint="api/report-conversation.php" data-csrf="<?= htmlspecialchars($_SESSION['report_csrf']) ?>"
    data-viewer-role="admin" data-last-message-id="<?= $messages ? (int) end($messages)['id'] : 0 ?>">
    <header class="report-conversation__header">
        <a class="report-conversation__back" href="reports.php" aria-label="Retour aux signalements"><i class="bi bi-arrow-left"></i></a>
        <span class="report-conversation__avatar"><i class="bi bi-person"></i></span>
        <div class="report-conversation__identity">
            <strong><?= htmlspecialchars($activeReport['email']) ?></strong>
            <small>Dossier ouvert le <?= htmlspecialchars(date('d/m/Y à H:i', strtotime($activeReport['created_at']))) ?></small>
        </div>
        <?php if ($activeReport['status'] === 'open'): ?>
            <form method="POST" onsubmit="return confirm('Fermer ce dossier comme résolu ?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['report_csrf']) ?>">
                <input type="hidden" name="report_id" value="<?= htmlspecialchars($activeReport['id']) ?>">
                <button type="submit" name="close_report" class="report-resolve-button" aria-label="Marquer comme résolu" title="Marquer comme résolu">
                    <i class="bi bi-trash3-fill" aria-hidden="true"></i>
                </button>
            </form>
        <?php else: ?>
            <span class="report-status report-status--closed">Résolu</span>
        <?php endif; ?>
    </header>
    <div class="report-conversation__body">
        <div class="report-chat-date"><?= htmlspecialchars(date('d/m/Y à H:i', strtotime($activeReport['created_at']))) ?></div>
        <article class="report-chat-message report-chat-message--admin">
            <div class="report-chat-message__bubble"><?= nl2br(htmlspecialchars($activeReport['message'])) ?></div>
            <time><?= htmlspecialchars(date('H:i', strtotime($activeReport['created_at']))) ?></time>
        </article>
        <?php foreach ($messages as $message): ?>
            <article class="report-chat-message <?= $message['sender_type'] === 'admin' ? 'report-chat-message--user' : 'report-chat-message--admin' ?>">
                <div class="report-chat-message__bubble">
                    <?php if ($message['sender_type'] === 'admin'): ?><small class="report-chat-message__sender">Administration</small><?php endif; ?>
                    <?= nl2br(htmlspecialchars($message['message'])) ?>
                </div>
                <time><?= htmlspecialchars(date('H:i · d/m/Y', strtotime($message['created_at']))) ?></time>
            </article>
        <?php endforeach; ?>
    </div>
    <p class="report-conversation__error" data-conversation-error role="alert" hidden></p>
    <?php if ($activeReport['status'] === 'open'): ?>
        <div class="report-conversation__admin-actions">
            <form class="report-conversation__composer" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['report_csrf']) ?>">
                <input type="hidden" name="report_id" value="<?= htmlspecialchars($activeReport['id']) ?>">
                <label class="visually-hidden" for="reply_message">Écrire une réponse</label>
                <textarea id="reply_message" name="reply_message" rows="1" maxlength="4000" placeholder="Répondre au dossier…" required></textarea>
                <button type="submit" name="admin_reply" aria-label="Envoyer la réponse"><i class="bi bi-send-fill"></i></button>
            </form>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php require 'includes/general/footer.php'; ?>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/report-conversation.js?v=202610281045"></script>
</body>
</html>
