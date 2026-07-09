<?php
if (empty($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$accountId = (int) $_SESSION['user_id'];
$errors = [];
$success = null;
$reportCountThisWeek = 0;

$pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS signalements_wtc (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        subject VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        FOREIGN KEY (account_id) REFERENCES account_wtc(id) ON DELETE CASCADE,
        INDEX (account_id),
        INDEX (created_at)
    )
SQL);

$weekStart = (new DateTimeImmutable('monday this week'))->setTime(0, 0, 0);
$weekEnd = (new DateTimeImmutable('sunday this week'))->setTime(23, 59, 59);

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM signalements_wtc WHERE account_id = ? AND created_at BETWEEN ? AND ?');
$countStmt->execute([$accountId, $weekStart->format('Y-m-d H:i:s'), $weekEnd->format('Y-m-d H:i:s')]);
$reportCountThisWeek = (int) $countStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $reportSubject = trim($_POST['report_subject'] ?? '');
    $reportMessage = trim($_POST['report_message'] ?? '');

    if ($reportSubject === '') {
        $errors[] = 'Le sujet est obligatoire.';
    } elseif (mb_strlen($reportSubject) > 150) {
        $errors[] = 'Le sujet ne doit pas dépasser 150 caractères.';
    }

    if ($reportMessage === '') {
        $errors[] = 'Le message est obligatoire.';
    } elseif (mb_strlen($reportMessage) < 10) {
        $errors[] = 'Le message doit contenir au moins 10 caractères.';
    } elseif (mb_strlen($reportMessage) > 4000) {
        $errors[] = 'Le message ne doit pas dépasser 4000 caractères.';
    }

    if ($reportCountThisWeek >= 3) {
        $errors[] = 'Tu as déjà atteint la limite de 3 signalements cette semaine.';
    }

    if (empty($errors)) {
        $insertReportStmt = $pdo->prepare(
            'INSERT INTO signalements_wtc (account_id, subject, message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)'
        );
        $insertReportStmt->execute([
            $accountId,
            $reportSubject,
            $reportMessage,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);

        $userStmt = $pdo->prepare('SELECT firstname, lastname, email FROM account_wtc WHERE id = ?');
        $userStmt->execute([$accountId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        $mailResult = sendReportNotificationEmail(
            $reportSubject,
            $reportMessage,
            trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
            $user['email'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        if (!empty($mailResult['success'])) {
            $success = 'Ton signalement a bien été envoyé. Merci pour ton aide.';
        } else {
            $success = 'Ton signalement a bien été enregistré, mais l’email de notification n’a pas pu être envoyé.';
            error_log('Échec envoi mail signalement : ' . ($mailResult['error'] ?? 'inconnu'));
        }

        $reportCountThisWeek++;
    }
}

$pageTitle = 'Warriors Training Club - Signaler un problème';