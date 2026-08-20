<?php
ini_set('session.use_strict_mode', '1');
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Lax');
// Default session lifetime: 14 days (configurable via app settings)
// Try to load DB-backed settings; if not available, fallback to 14 days.
$defaultDays = 14;
$defaultSeconds = $defaultDays * 86400;
$timeoutSeconds = $defaultSeconds;
try {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/app-settings.php';
    $cfg = get_setting('session_timeout_seconds', (string) $defaultSeconds);
    $timeoutSeconds = (int) $cfg > 0 ? (int) $cfg : $defaultSeconds;
} catch (Throwable $e) {
    // ignore — use default
}

ini_set('session.gc_maxlifetime', (string) $timeoutSeconds);

session_set_cookie_params([
    'lifetime' => $timeoutSeconds,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure we have a session creation timestamp (used for client-side timeout calculations)
if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
}

require_once __DIR__ . '/clear-cache.php';

// Global error handler - redirects users to the friendly error page on fatal errors
require_once __DIR__ . '/error-handler.php';

header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
header('Pragma: no-cache');
header('Expires: -1');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
