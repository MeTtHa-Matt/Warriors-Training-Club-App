<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
dashboardSendHeaders();
dashboardStartSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

if (!dashboardCheckCsrf()) {
    http_response_code(403);
    exit('Requête refusée.');
}

$action = (string) ($_POST['action'] ?? 'login');
if ($action === 'logout') {
    dashboardDestroySession();
    header('Location: index.php', true, 303);
    exit;
}

$username = (string) ($_POST['username'] ?? '');
$password = (string) ($_POST['password'] ?? '');
$expectedUsername = dashboardSetting('DASHBOARD_USERNAME');
$passwordHash = dashboardSetting('DASHBOARD_PASSWORD_HASH');

$allowed = dashboardLoginAllowed();
$passwordMatches = false;
if ($allowed && strlen($username) <= 128 && strlen($password) <= 1024) {
    if ($passwordHash !== '' && password_get_info($passwordHash)['algo'] !== null) {
        $passwordMatches = password_verify($password, $passwordHash);
    }
}

if ($allowed && $expectedUsername !== '' && hash_equals($expectedUsername, $username) && $passwordMatches) {
    session_regenerate_id(true);
    $_SESSION['dashboard_authenticated'] = true;
    $_SESSION['dashboard_login_at'] = time();
    $_SESSION['dashboard_last_activity'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    header('Location: index.php', true, 303);
    exit;
}

$_SESSION['login_error'] = $allowed
    ? 'Identifiants incorrects.'
    : 'Connexion temporairement indisponible. Réessayez plus tard.';
header('Location: index.php', true, 303);
exit;