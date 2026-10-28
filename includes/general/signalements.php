<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/report-schema.php';

ensureReportSchema($pdo);

$errors = [];
$success = $_SESSION['report_flash'] ?? null;
unset($_SESSION['report_flash']);
$step = $_SESSION['report_step'] ?? 'message';
$reportData = $_SESSION['report_data'] ?? [];
$now = time();
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId > 0 && in_array($step, ['email', 'code'], true)) {
    unset($_SESSION['report_data'], $_SESSION['report_verification'], $_SESSION['report_code_sent_at'], $_SESSION['report_step']);
    $step = 'message';
    $reportData = [];
}

if (empty($_SESSION['report_csrf'])) {
    $_SESSION['report_csrf'] = bin2hex(random_bytes(32));
}

if (empty($_COOKIE['wtc_report_device']) || !preg_match('/^[a-f0-9]{64}$/', $_COOKIE['wtc_report_device'])) {
    $deviceToken = bin2hex(random_bytes(32));
    setcookie('wtc_report_device', $deviceToken, [
        'expires' => $now + 31536000,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
} else {
    $deviceToken = $_COOKIE['wtc_report_device'];
}

$deviceHash = hash('sha256', $deviceToken);
$ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
$readReports = static function (string $file): array {
    if (!is_file($file)) {
        return [];
    }

    $reports = json_decode(file_get_contents($file) ?: '[]', true);
    return is_array($reports) ? $reports : [];
};
$recentReports = array_filter($readReports(__DIR__ . '/../../data/reports.json'), static function (array $report) use ($now, $deviceHash, $ipHash): bool {
    $createdAt = strtotime($report['created_at'] ?? '') ?: 0;
    return $createdAt >= $now - 86400
        && (($report['device_hash'] ?? '') === $deviceHash || ($report['ip_hash'] ?? '') === $ipHash);
});
$recentDatabaseReports = $pdo->prepare(
    'SELECT COUNT(*) FROM dashboard_reports
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) AND (device_hash = ? OR ip_hash = ?)'
);
$recentDatabaseReports->execute([$deviceHash, $ipHash]);
$recentReportCount = count($recentReports) + (int) $recentDatabaseReports->fetchColumn();

$saveReport = static function (string $email, ?int $ownerId, string $message) use ($pdo, $deviceHash, $ipHash): string {
    $reportId = bin2hex(random_bytes(16));
    $statement = $pdo->prepare(
        'INSERT INTO dashboard_reports (id, email, message, device_hash, ip_hash, owner_user_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $statement->execute([$reportId, $email, $message, $deviceHash, $ipHash, $ownerId]);
    return $reportId;
};

$userCanAccessReport = static function (string $reportId) use ($pdo, $userId, $deviceHash): bool {
    $statement = $pdo->prepare(
        'SELECT id FROM dashboard_reports
         WHERE id = ? AND (
            owner_user_id = ? OR (owner_user_id IS NULL AND device_hash = ?)
         )'
    );
    $statement->execute([$reportId, $userId ?: -1, $deviceHash]);
    return (bool) $statement->fetchColumn();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['report_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'La session a expiré. Recharge la page et réessaie.';
    } elseif (isset($_POST['prepare_report'])) {
        $message = trim((string) ($_POST['report_message'] ?? ''));
        if (mb_strlen($message) < 10 || mb_strlen($message) > 4000) {
            $errors[] = 'Le signalement doit contenir entre 10 et 4 000 caractères.';
        } elseif ($recentReportCount >= 3) {
            $errors[] = 'Trop de signalements depuis cet appareil. Réessaie demain.';
        } elseif ($userId > 0) {
            try {
                $account = $pdo->prepare('SELECT email FROM account_wtc WHERE id = ?');
                $account->execute([$userId]);
                $email = $account->fetchColumn();
                if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Adresse e-mail introuvable pour le compte connecté.');
                }

                $reportId = $saveReport($email, $userId, $message);
                $_SESSION['report_flash'] = 'Ton signalement a bien été envoyé. Merci pour ton aide.';
                header('Location: signalements.php?report=' . rawurlencode($reportId), true, 303);
                exit;
            } catch (Throwable $error) {
                error_log('[reports] ' . $error->getMessage());
                $errors[] = 'Le signalement n’a pas pu être enregistré. Réessaie plus tard.';
            }
        } else {
            $_SESSION['report_data'] = ['message' => $message];
            $_SESSION['report_step'] = 'email';
            $step = 'email';
            $reportData = $_SESSION['report_data'];
        }
    } elseif (isset($_POST['send_code'])) {
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        if (!$email || mb_strlen($email) > 254) {
            $errors[] = 'Renseigne une adresse e-mail valide.';
        } elseif (!empty($_SESSION['report_code_sent_at']) && $now - (int) $_SESSION['report_code_sent_at'] < 60) {
            $errors[] = 'Attends quelques secondes avant de demander un nouveau code.';
        } elseif (empty($reportData['message'])) {
            $errors[] = 'Le signalement est incomplet. Recommence.';
            $step = 'message';
        } else {
            $code = (string) random_int(100000, 999999);
            $_SESSION['report_verification'] = [
                'email' => strtolower($email),
                'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                'expires_at' => $now + 600,
                'attempts' => 0,
            ];
            $mailResult = sendReportVerificationEmail($email, $code);
            if (!empty($mailResult['success'])) {
                $_SESSION['report_code_sent_at'] = $now;
                $_SESSION['report_step'] = 'code';
                $step = 'code';
            } else {
                unset($_SESSION['report_verification']);
                $errors[] = 'Le code n’a pas pu être envoyé. Réessaie plus tard.';
                error_log('Échec envoi code signalement : ' . ($mailResult['error'] ?? 'inconnu'));
            }
        }
    } elseif (isset($_POST['verify_report'])) {
        $verification = $_SESSION['report_verification'] ?? [];
        $code = trim((string) ($_POST['verification_code'] ?? ''));
        if (empty($verification) || ($verification['expires_at'] ?? 0) < $now) {
            $errors[] = 'Ce code a expiré. Demande un nouveau code.';
            $step = 'email';
        } elseif (($verification['attempts'] ?? 0) >= 5 || !password_verify($code, $verification['code_hash'])) {
            $_SESSION['report_verification']['attempts'] = ($verification['attempts'] ?? 0) + 1;
            $errors[] = 'Code incorrect.';
            $step = 'code';
        } elseif (empty($reportData['message'])) {
            $errors[] = 'Le signalement est incomplet. Recommence.';
            $step = 'message';
        } elseif ($recentReportCount >= 3) {
            $errors[] = 'Trop de signalements depuis cet appareil. Réessaie demain.';
            $step = 'message';
        } else {
            try {
                $reportId = $saveReport($verification['email'], null, $reportData['message']);
                unset($_SESSION['report_data'], $_SESSION['report_verification'], $_SESSION['report_step'], $_SESSION['report_code_sent_at']);
                $_SESSION['report_flash'] = 'Ton signalement a bien été envoyé. Merci pour ton aide.';
                header('Location: signalements.php?report=' . rawurlencode($reportId), true, 303);
                exit;
            } catch (Throwable $error) {
                error_log('[reports] ' . $error->getMessage());
                $errors[] = 'Le signalement n’a pas pu être enregistré. Réessaie plus tard.';
                $step = 'code';
            }
        }
    } elseif (isset($_POST['send_reply'])) {
        $reportId = (string) ($_POST['report_id'] ?? '');
        $message = trim((string) ($_POST['reply_message'] ?? ''));
        if (!preg_match('/^[a-f0-9]{32}$/', $reportId) || !$userCanAccessReport($reportId)) {
            http_response_code(404);
            $errors[] = 'Ce dossier est introuvable.';
        } elseif (mb_strlen($message) < 1 || mb_strlen($message) > 4000) {
            $errors[] = 'Le message doit contenir entre 1 et 4 000 caractères.';
        } else {
            $status = $pdo->prepare('SELECT status FROM dashboard_reports WHERE id = ?');
            $status->execute([$reportId]);
            if ($status->fetchColumn() !== 'open') {
                $errors[] = 'Ce dossier est fermé et ne peut plus recevoir de réponse.';
            } else {
                $reply = $pdo->prepare(
                    "INSERT INTO dashboard_report_messages (report_id, sender_type, sender_id, message)
                     VALUES (?, 'user', ?, ?)"
                );
                $reply->execute([$reportId, $userId ?: null, $message]);
                header('Location: signalements.php?report=' . rawurlencode($reportId), true, 303);
                exit;
            }
        }
    }
}

$reportId = (string) ($_GET['report'] ?? $_POST['report_id'] ?? '');
$activeReport = null;
$messages = [];
if ($reportId !== '') {
    if (!preg_match('/^[a-f0-9]{32}$/', $reportId) || !$userCanAccessReport($reportId)) {
        http_response_code(404);
        $errors[] = 'Ce dossier est introuvable.';
        $reportId = '';
    } else {
        $statement = $pdo->prepare('SELECT id, email, message, created_at, status FROM dashboard_reports WHERE id = ?');
        $statement->execute([$reportId]);
        $activeReport = $statement->fetch(PDO::FETCH_ASSOC);
        $messageQuery = $pdo->prepare(
            'SELECT id, sender_type, message, created_at FROM dashboard_report_messages WHERE report_id = ? ORDER BY id ASC'
        );
        $messageQuery->execute([$reportId]);
        $messages = $messageQuery->fetchAll(PDO::FETCH_ASSOC);
        $latestMessageId = $messages ? (int) end($messages)['id'] : 0;
        $markViewed = $pdo->prepare(
            'UPDATE dashboard_reports SET user_last_viewed_message_id = GREATEST(user_last_viewed_message_id, ?) WHERE id = ?'
        );
        $markViewed->execute([$latestMessageId, $reportId]);
    }
}

$userReports = [];
if ($userId > 0) {
    $statement = $pdo->prepare(
        "SELECT id, email, message, created_at, status FROM dashboard_reports
         WHERE owner_user_id = ? OR (owner_user_id IS NULL AND device_hash = ?)
         ORDER BY (status = 'open') DESC, created_at DESC"
    );
    $statement->execute([$userId, $deviceHash]);
    $userReports = $statement->fetchAll(PDO::FETCH_ASSOC);
} else {
    $statement = $pdo->prepare(
        "SELECT id, email, message, created_at, status FROM dashboard_reports
         WHERE owner_user_id IS NULL AND device_hash = ?
         ORDER BY (status = 'open') DESC, created_at DESC"
    );
    $statement->execute([$deviceHash]);
    $userReports = $statement->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Warriors Training Club - Signaler un problème';
