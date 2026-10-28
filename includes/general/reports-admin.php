<?php
require_once __DIR__ . '/session-config.php';
require_once __DIR__ . '/verifications.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/report-schema.php';

if (empty($_SESSION['user_id']) || (int) ($_SESSION['admin'] ?? 0) !== 1) {
    header('Location: index.php');
    exit;
}

ensureReportSchema($pdo);
if (empty($_SESSION['report_csrf'])) {
    $_SESSION['report_csrf'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = $_SESSION['admin_report_flash'] ?? null;
unset($_SESSION['admin_report_flash']);
$adminId = (int) $_SESSION['user_id'];
$reportId = (string) ($_GET['report'] ?? $_POST['report_id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['report_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'La session a expiré. Recharge la page et réessaie.';
    } elseif (!preg_match('/^[a-f0-9]{32}$/', $reportId)) {
        $errors[] = 'Ce dossier est introuvable.';
    } elseif (isset($_POST['admin_reply']) || isset($_POST['close_report'])) {
        $reportQuery = $pdo->prepare('SELECT status FROM dashboard_reports WHERE id = ?');
        $reportQuery->execute([$reportId]);
        $status = $reportQuery->fetchColumn();
        if ($status === false) {
            http_response_code(404);
            $errors[] = 'Ce dossier est introuvable.';
        } elseif ($status !== 'open') {
            $errors[] = 'Ce dossier est déjà fermé.';
        } elseif (isset($_POST['admin_reply'])) {
            $message = trim((string) ($_POST['reply_message'] ?? ''));
            if (mb_strlen($message) < 1 || mb_strlen($message) > 4000) {
                $errors[] = 'Le message doit contenir entre 1 et 4 000 caractères.';
            } else {
                $reply = $pdo->prepare(
                    "INSERT INTO dashboard_report_messages (report_id, sender_type, sender_id, message)
                     VALUES (?, 'admin', ?, ?)"
                );
                $reply->execute([$reportId, $adminId, $message]);
                $_SESSION['admin_report_flash'] = 'Ta réponse a été envoyée.';
                header('Location: reports.php?report=' . rawurlencode($reportId), true, 303);
                exit;
            }
        } else {
            $close = $pdo->prepare("UPDATE dashboard_reports SET status = 'closed' WHERE id = ?");
            $close->execute([$reportId]);
            $_SESSION['admin_report_flash'] = 'Le dossier a été fermé.';
            header('Location: reports.php', true, 303);
            exit;
        }
    }
}

$activeReport = null;
$messages = [];
if ($reportId !== '') {
    if (!preg_match('/^[a-f0-9]{32}$/', $reportId)) {
        http_response_code(404);
        $errors[] = 'Ce dossier est introuvable.';
        $reportId = '';
    } else {
        $reportQuery = $pdo->prepare('SELECT id, email, message, created_at, status FROM dashboard_reports WHERE id = ?');
        $reportQuery->execute([$reportId]);
        $activeReport = $reportQuery->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$activeReport) {
            http_response_code(404);
            $errors[] = 'Ce dossier est introuvable.';
            $reportId = '';
        } else {
            $messageQuery = $pdo->prepare(
                'SELECT id, sender_type, message, created_at FROM dashboard_report_messages WHERE report_id = ? ORDER BY id ASC'
            );
            $messageQuery->execute([$reportId]);
            $messages = $messageQuery->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

$reports = $pdo->query(
    "SELECT r.id, r.email, r.message, r.created_at, r.status,
        (SELECT m.message FROM dashboard_report_messages m WHERE m.report_id = r.id ORDER BY m.id DESC LIMIT 1) AS latest_message,
        (SELECT m.created_at FROM dashboard_report_messages m WHERE m.report_id = r.id ORDER BY m.id DESC LIMIT 1) AS latest_message_at
     FROM dashboard_reports r WHERE r.status = 'open' ORDER BY r.created_at DESC LIMIT 500"
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Warriors Training Club - Signalements';
