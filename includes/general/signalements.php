<?php
$errors = [];
$success = null;
$step = $_SESSION['report_step'] ?? 'message';
$reportData = $_SESSION['report_data'] ?? [];
$reportFile = __DIR__ . '/../../data/reports.json';
$now = time();

if (empty($_SESSION['report_csrf'])) $_SESSION['report_csrf'] = bin2hex(random_bytes(32));
if (empty($_COOKIE['wtc_report_device']) || !preg_match('/^[a-f0-9]{64}$/', $_COOKIE['wtc_report_device'])) {
    $deviceToken = bin2hex(random_bytes(32));
    setcookie('wtc_report_device', $deviceToken, [
        'expires' => $now + 31536000, 'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax',
    ]);
} else $deviceToken = $_COOKIE['wtc_report_device'];
$deviceHash = hash('sha256', $deviceToken);
$ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

$readReports = static function (string $file): array {
    if (!is_file($file)) return [];
    $reports = json_decode(file_get_contents($file) ?: '[]', true);
    return is_array($reports) ? $reports : [];
};
$writeReports = static function (string $file, array $reports): bool {
    $handle = fopen($file, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) return false;
    ftruncate($handle, 0); rewind($handle);
    $written = fwrite($handle, json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($handle); flock($handle, LOCK_UN); fclose($handle);
    return $written !== false;
};
$reports = $readReports($reportFile);
$recentReports = array_filter($reports, static function (array $report) use ($now, $deviceHash, $ipHash): bool {
    $createdAt = strtotime($report['created_at'] ?? '') ?: 0;
    return $createdAt >= $now - 86400 && (($report['device_hash'] ?? '') === $deviceHash || ($report['ip_hash'] ?? '') === $ipHash);
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['report_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'La session a expiré. Recharge la page et réessaie.';
    } elseif (isset($_POST['prepare_report'])) {
        $message = trim((string) ($_POST['report_message'] ?? ''));
        if (mb_strlen($message) < 10 || mb_strlen($message) > 4000) $errors[] = 'Le signalement doit contenir entre 10 et 4 000 caractères.';
        elseif (count($recentReports) >= 3) $errors[] = 'Trop de signalements depuis cet appareil. Réessaie demain.';
        else { $_SESSION['report_data'] = ['message' => $message]; $_SESSION['report_step'] = 'email'; $step = 'email'; $reportData = $_SESSION['report_data']; }
    } elseif (isset($_POST['send_code'])) {
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        if (!$email || mb_strlen($email) > 254) $errors[] = 'Renseigne une adresse e-mail valide.';
        elseif (!empty($_SESSION['report_code_sent_at']) && $now - (int) $_SESSION['report_code_sent_at'] < 60) $errors[] = 'Attends quelques secondes avant de demander un nouveau code.';
        elseif (empty($reportData['message'])) { $errors[] = 'Le signalement est incomplet. Recommence.'; $step = 'message'; }
        else {
            $code = (string) random_int(100000, 999999);
            $_SESSION['report_verification'] = ['email' => strtolower($email), 'code_hash' => password_hash($code, PASSWORD_DEFAULT), 'expires_at' => $now + 600, 'attempts' => 0];
            $mailResult = sendReportVerificationEmail($email, $code);
            if (!empty($mailResult['success'])) { $_SESSION['report_code_sent_at'] = $now; $_SESSION['report_step'] = 'code'; $step = 'code'; }
            else { unset($_SESSION['report_verification']); $errors[] = 'Le code n’a pas pu être envoyé. Réessaie plus tard.'; error_log('Échec envoi code signalement : ' . ($mailResult['error'] ?? 'inconnu')); }
        }
    } elseif (isset($_POST['verify_report'])) {
        $verification = $_SESSION['report_verification'] ?? [];
        $code = trim((string) ($_POST['verification_code'] ?? ''));
        if (empty($verification) || ($verification['expires_at'] ?? 0) < $now) { $errors[] = 'Ce code a expiré. Demande un nouveau code.'; $step = 'email'; }
        elseif (($verification['attempts'] ?? 0) >= 5 || !password_verify($code, $verification['code_hash'])) { $_SESSION['report_verification']['attempts'] = ($verification['attempts'] ?? 0) + 1; $errors[] = 'Code incorrect.'; $step = 'code'; }
        elseif (count($recentReports) >= 3) { $errors[] = 'Trop de signalements depuis cet appareil. Réessaie demain.'; $step = 'message'; }
        else {
            $reports[] = ['id' => bin2hex(random_bytes(8)), 'email' => $verification['email'], 'message' => $reportData['message'], 'created_at' => gmdate('c'), 'device_hash' => $deviceHash, 'ip_hash' => $ipHash];
            if (!$writeReports($reportFile, $reports)) { $errors[] = 'Le signalement n’a pas pu être enregistré. Réessaie plus tard.'; $step = 'code'; }
            else { unset($_SESSION['report_data'], $_SESSION['report_verification'], $_SESSION['report_step'], $_SESSION['report_code_sent_at']); $success = 'Ton signalement a bien été envoyé. Merci pour ton aide.'; $step = 'message'; }
        }
    }
}

$pageTitle = 'Warriors Training Club - Signaler un problème';