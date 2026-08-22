<?php
require_once __DIR__ . '/../general/session-config.php';
require_once __DIR__ . '/../general/db.php';
require_once __DIR__ . '/../general/persistent-auth.php';

$tokenManager = new PersistentToken($pdo);

if (!empty($_SESSION['user_id'])) {
    try {
        $logoutUserId = (int) $_SESSION['user_id'];
        $logoutStmt = $pdo->prepare('UPDATE account_wtc SET last_seen = NULL WHERE id = ?');
        $logoutStmt->execute([$logoutUserId]);
    } catch (Throwable $e) {
        error_log('[deconnexion_process] impossible de marquer l’utilisateur comme inactif: ' . $e->getMessage());
    }
}

// Fast response to client: clear session cookie and send redirect immediately,
// then perform DB cleanup (token removal) after finishing the response.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Choose redirect target
$next = basename($_GET['next'] ?? '');
$redirect = ($next === 'connexion.php') ? '../../connexion.php' : '../../index.php';

// Send redirect header and flush response to client
header('Location: ' . $redirect);
header('Content-Length: 0');
// ensure session is saved
session_write_close();
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Now perform heavier cleanup (can run after response delivered)
try {
    $tokenManager->clear();
} catch (Throwable $e) {
    error_log('[deconnexion_process] token clear error: ' . $e->getMessage());
}

// Finally destroy session as a safeguard
@session_start();
$_SESSION = [];
session_destroy();

exit;

