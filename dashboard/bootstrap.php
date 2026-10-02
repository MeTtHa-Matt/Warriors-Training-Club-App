<?php
declare(strict_types=1);

const DASHBOARD_ROOT = __DIR__;
const DASHBOARD_SESSION_IDLE = 1800;
const DASHBOARD_SESSION_MAX = 28800;

function dashboardConfig(): array
{
    static $config;
    if (isset($config)) {
        return $config;
    }

    $fileConfig = [];
    $envFile = DASHBOARD_ROOT . '/.env';
    if (is_file($envFile) && is_readable($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES);
        foreach (is_array($lines) ? $lines : [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }
            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
                continue;
            }

            $value = trim($matches[2]);
            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            } else {
                $value = preg_replace('/\s+[;#].*$/', '', $value) ?? $value;
            }
            $fileConfig[$matches[1]] = $value;
        }
    }

    $config = [];
    foreach ($fileConfig as $key => $value) {
        $config[$key] = is_string($value) ? trim($value) : '';
    }
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'DASHBOARD_') || str_starts_with($key, 'DB_') || str_starts_with($key, 'ANALYTICS_MAINTENANCE_DB_') || str_starts_with($key, 'MAIL_') || str_starts_with($key, 'GITHUB_')) {
            if (is_string($value) && $value !== '') {
                $config[$key] = $value;
            }
        }
    }
    foreach ($_ENV as $key => $value) {
        if ((str_starts_with($key, 'DASHBOARD_') || str_starts_with($key, 'DB_') || str_starts_with($key, 'ANALYTICS_MAINTENANCE_DB_') || str_starts_with($key, 'MAIL_') || str_starts_with($key, 'GITHUB_')) && is_string($value) && $value !== '') {
            $config[$key] = $value;
        }
    }
    foreach (['DASHBOARD_USERNAME', 'DASHBOARD_PASSWORD_HASH', 'DASHBOARD_COOKIE_SECURE', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'ANALYTICS_MAINTENANCE_DB_HOST', 'ANALYTICS_MAINTENANCE_DB_PORT', 'ANALYTICS_MAINTENANCE_DB_DATABASE', 'ANALYTICS_MAINTENANCE_DB_USERNAME', 'ANALYTICS_MAINTENANCE_DB_PASSWORD', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM', 'MAIL_FROM_NAME', 'MAIL_ENCRYPTION', 'GITHUB_TOKEN', 'GITHUB_API_TOKEN'] as $key) {
        $value = getenv($key);
        if (is_string($value) && $value !== '') {
            $config[$key] = $value;
        }
    }

    return $config;
}

function dashboardSetting(string $key, string $default = ''): string
{
    $value = dashboardConfig()[$key] ?? $default;
    return is_string($value) ? $value : $default;
}

define('DASHBOARD_STORAGE', sys_get_temp_dir() . '/wtc-dashboard-' . substr(hash('sha256', __DIR__), 0, 24));

function dashboardSecureRequest(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function dashboardStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('wtc_dashboard');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => dashboardSetting('DASHBOARD_COOKIE_SECURE') === '1' || (dashboardSetting('DASHBOARD_COOKIE_SECURE') !== '0' && dashboardSecureRequest()),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function dashboardSendHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    if (dashboardSecureRequest()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function dashboardEnsureStorage(): bool
{
    if (!is_dir(DASHBOARD_STORAGE)) {
        @mkdir(DASHBOARD_STORAGE, 0700, true);
    }
    if (is_dir(DASHBOARD_STORAGE)) {
        @chmod(DASHBOARD_STORAGE, 0700);
    }
    return is_dir(DASHBOARD_STORAGE) && is_writable(DASHBOARD_STORAGE);
}

function dashboardIsAuthenticated(): bool
{
    dashboardStartSession();
    if (empty($_SESSION['dashboard_authenticated'])) {
        return false;
    }

    $now = time();
    $lastActivity = (int) ($_SESSION['dashboard_last_activity'] ?? 0);
    $loginAt = (int) ($_SESSION['dashboard_login_at'] ?? 0);
    if ($lastActivity < 1 || $loginAt < 1 || $now - $lastActivity > DASHBOARD_SESSION_IDLE || $now - $loginAt > DASHBOARD_SESSION_MAX) {
        dashboardDestroySession();
        return false;
    }

    $_SESSION['dashboard_last_activity'] = $now;
    return true;
}

function dashboardDestroySession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Strict',
        ]);
    }
    session_destroy();
}

function dashboardRequireAuth(): void
{
    if (!dashboardIsAuthenticated()) {
        dashboardJson(['error' => 'Authentification requise.'], 401);
    }
}

function dashboardJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function dashboardCheckCsrf(): bool
{
    $sent = (string) ($_POST['csrf'] ?? '');
    $expected = (string) ($_SESSION['csrf'] ?? '');
    return $sent !== '' && $expected !== '' && hash_equals($expected, $sent);
}

function dashboardLoginAllowed(): bool
{
    if (!dashboardEnsureStorage()) {
        return false;
    }

    $file = DASHBOARD_STORAGE . '/login-attempts.json';
    $lock = @fopen(DASHBOARD_STORAGE . '/login-attempts.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        return false;
    }

    $attempts = is_file($file) ? json_decode((string) @file_get_contents($file), true) : [];
    $attempts = is_array($attempts) ? $attempts : [];
    $now = time();
    $key = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $recent = array_values(array_filter($attempts[$key] ?? [], static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - 900));
    $allowed = count($recent) < 5;
    if (!$allowed) {
        $attempts[$key] = $recent;
    } else {
        $recent[] = $now;
        $attempts[$key] = $recent;
    }
    foreach ($attempts as $ipHash => $timestamps) {
        $fresh = array_values(array_filter(is_array($timestamps) ? $timestamps : [], static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - 900));
        if ($fresh === []) {
            unset($attempts[$ipHash]);
        } else {
            $attempts[$ipHash] = $fresh;
        }
    }
    @file_put_contents($file, json_encode($attempts), LOCK_EX);
    @chmod($file, 0600);
    flock($lock, LOCK_UN);
    fclose($lock);

    return $allowed;
}

function dashboardDatabase(): ?PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = dashboardSetting('DB_HOST');
    $database = dashboardSetting('DB_DATABASE');
    $username = dashboardSetting('DB_USERNAME');
    if ($host === '' || $database === '' || $username === '') {
        return null;
    }

    $port = dashboardSetting('DB_PORT', '3306');
    if (!ctype_digit($port) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $host) || !preg_match('/^[A-Za-z0-9_]+$/', $database)) {
        return null;
    }

    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            $username,
            dashboardSetting('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 3]
        );
        return $pdo;
    } catch (Throwable $error) {
        error_log('[dashboard] Database connection failed: ' . $error->getMessage());
        return null;
    }
}

function dashboardAnalyticsReport(?string &$status = null, int $days = 28): ?array
{
    if (!in_array($days, [7, 28, 90], true)) {
        $days = 28;
    }
    $cachePath = DASHBOARD_STORAGE . '/analytics-report-v2-' . $days . '.json';
    if (is_file($cachePath) && filemtime($cachePath) > time() - 60) {
        $cached = json_decode((string) @file_get_contents($cachePath), true);
        if (is_array($cached)) {
            $status = 'ready_cached';
            return $cached;
        }
    }

    $pdo = dashboardDatabase();
    if ($pdo === null) {
        $status = 'database_unavailable';
        return null;
    }

    try {
        $pdo->exec('SET @analytics_period_days = ' . $days);
        $summary = $pdo->query(
            "SELECT
                COUNT(DISTINCT CASE WHEN event_type = 'page_view' THEN session_hash END) AS sessions,
                COUNT(DISTINCT CASE WHEN event_type = 'engagement' THEN session_hash END) AS engaged_sessions,
                SUM(event_type = 'page_view') AS page_views,
                SUM(event_type = 'click') AS clicks,
                SUM(event_type = 'download') AS downloads,
                SUM(event_type = 'form_submit') AS form_submissions,
                SUM(event_type = 'click' AND target_host IS NOT NULL AND target_host <> '') AS outbound_clicks,
                COUNT(*) AS event_count
             FROM analytics_events
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL @analytics_period_days DAY)"
        )->fetch();

        $daily = $pdo->query(
            "SELECT DATE_FORMAT(created_at, '%Y%m%d') AS date,
                SUM(event_type = 'page_view') AS views,
                COUNT(DISTINCT CASE WHEN event_type = 'page_view' THEN session_hash END) AS sessions
             FROM analytics_events
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL @analytics_period_days DAY)
             GROUP BY DATE(created_at) ORDER BY DATE(created_at)"
        )->fetchAll();
        $hourly = $pdo->query(
            "SELECT HOUR(created_at) AS hour, COUNT(*) AS visits
             FROM analytics_events
                 WHERE event_type = 'page_view' AND created_at >= DATE_SUB(NOW(), INTERVAL @analytics_period_days DAY)
             GROUP BY HOUR(created_at) ORDER BY hour"
        )->fetchAll();
        $pages = $pdo->query(
            "SELECT page_path AS path, COUNT(*) AS views,
                COUNT(DISTINCT session_hash) AS sessions
             FROM analytics_events
             WHERE event_type = 'page_view' AND created_at >= DATE_SUB(NOW(), INTERVAL @analytics_period_days DAY)
             GROUP BY page_path ORDER BY views DESC LIMIT 8"
        )->fetchAll();
        $clicks = $pdo->query(
            "SELECT event_type AS type, target_type AS target, target_host AS host,
                target_path AS path, interaction_label AS label, COUNT(*) AS clicks
             FROM analytics_events
             WHERE event_type IN ('click', 'download') AND created_at >= DATE_SUB(NOW(), INTERVAL @analytics_period_days DAY)
             GROUP BY event_type, target_type, target_host, target_path, interaction_label
             ORDER BY clicks DESC LIMIT 10"
        )->fetchAll();
        $sources = $pdo->query(
            "SELECT referrer_host AS source, COUNT(*) AS visits
             FROM analytics_events
             WHERE event_type = 'page_view' AND referrer_host IS NOT NULL AND referrer_host <> ''
                 AND created_at >= DATE_SUB(NOW(), INTERVAL @analytics_period_days DAY)
             GROUP BY referrer_host ORDER BY visits DESC LIMIT 8"
        )->fetchAll();
        $devices = $pdo->query(
            "SELECT device_category AS name, COUNT(DISTINCT session_hash) AS visitors
             FROM analytics_events
             WHERE event_type = 'page_view' AND created_at >= DATE_SUB(NOW(), INTERVAL @analytics_period_days DAY)
             GROUP BY device_category ORDER BY visitors DESC"
        )->fetchAll();
        $browsers = $pdo->query(
            "SELECT browser_category AS name, COUNT(DISTINCT session_hash) AS visitors
             FROM analytics_events
             WHERE event_type = 'page_view' AND created_at >= DATE_SUB(NOW(), INTERVAL @analytics_period_days DAY)
             GROUP BY browser_category ORDER BY visitors DESC"
        )->fetchAll();
        $operatingSystems = $pdo->query(
            "SELECT operating_system AS name, COUNT(DISTINCT session_hash) AS sessions
             FROM analytics_events
             WHERE event_type = 'page_view' AND created_at >= DATE_SUB(NOW(), INTERVAL @analytics_period_days DAY)
             GROUP BY operating_system ORDER BY sessions DESC"
        )->fetchAll();
        $viewports = $pdo->query(
            "SELECT viewport_category AS name, COUNT(DISTINCT session_hash) AS sessions
             FROM analytics_events
             WHERE event_type = 'page_view' AND created_at >= DATE_SUB(NOW(), INTERVAL @analytics_period_days DAY)
             GROUP BY viewport_category ORDER BY sessions DESC"
        )->fetchAll();
        $forms = $pdo->query(
            "SELECT COALESCE(NULLIF(interaction_label, ''), 'Formulaire') AS label, COUNT(*) AS submissions
             FROM analytics_events
             WHERE event_type = 'form_submit' AND created_at >= DATE_SUB(NOW(), INTERVAL @analytics_period_days DAY)
             GROUP BY interaction_label ORDER BY submissions DESC LIMIT 8"
        )->fetchAll();
        $scroll = $pdo->query(
            "SELECT scroll_depth AS depth, COUNT(DISTINCT session_hash) AS sessions
             FROM analytics_events
             WHERE event_type = 'scroll_depth' AND created_at >= DATE_SUB(NOW(), INTERVAL @analytics_period_days DAY)
             GROUP BY scroll_depth ORDER BY scroll_depth"
        )->fetchAll();
        $performance = $pdo->query(
            "SELECT metric_name AS name, ROUND(AVG(metric_value), 1) AS average,
                ROUND(MAX(metric_value), 1) AS maximum, COUNT(*) AS samples
             FROM analytics_events
             WHERE event_type = 'performance' AND metric_value IS NOT NULL
                AND created_at >= DATE_SUB(NOW(), INTERVAL @analytics_period_days DAY)
             GROUP BY metric_name ORDER BY metric_name"
        )->fetchAll();
        $multiPageSessions = (int) $pdo->query(
            "SELECT COUNT(*) FROM (
                SELECT session_hash FROM analytics_events
                WHERE event_type = 'page_view' AND created_at >= DATE_SUB(NOW(), INTERVAL @analytics_period_days DAY)
                GROUP BY session_hash HAVING COUNT(*) > 1
            ) AS multi_page_sessions"
        )->fetchColumn();

        $sessions = (int) ($summary['sessions'] ?? 0);
        $pageViews = (int) ($summary['page_views'] ?? 0);
        $report = [
            'periodDays' => $days,
            'totals' => [
                'sessions' => $sessions,
                'engagedSessions' => (int) ($summary['engaged_sessions'] ?? 0),
                'multiPageSessions' => $multiPageSessions,
                'bounceRate' => $sessions > 0 ? max(0, $sessions - $multiPageSessions) / $sessions : 0,
                'pageViews' => $pageViews,
                'pagesPerSession' => $sessions > 0 ? $pageViews / $sessions : 0,
                'engagementRate' => $sessions > 0 ? (int) ($summary['engaged_sessions'] ?? 0) / $sessions : 0,
                'clickCount' => (int) ($summary['clicks'] ?? 0),
                'outboundClicks' => (int) ($summary['outbound_clicks'] ?? 0),
                'downloads' => (int) ($summary['downloads'] ?? 0),
                'formSubmissions' => (int) ($summary['form_submissions'] ?? 0),
                'eventCount' => (int) ($summary['event_count'] ?? 0),
            ],
            'daily' => $daily,
            'hourly' => $hourly,
            'pages' => $pages,
            'clicks' => $clicks,
            'sources' => $sources,
            'devices' => $devices,
            'browsers' => $browsers,
            'operatingSystems' => $operatingSystems,
            'viewports' => $viewports,
            'forms' => $forms,
            'scroll' => $scroll,
            'performance' => $performance,
        ];
        $status = 'ready';
        if (dashboardEnsureStorage()) {
            $temporaryPath = $cachePath . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($temporaryPath, (string) json_encode($report), LOCK_EX) !== false) {
                @chmod($temporaryPath, 0600);
                @rename($temporaryPath, $cachePath);
            }
        }
        return $report;
    } catch (Throwable $error) {
        $status = 'analytics_unavailable';
        error_log('[dashboard] First-party analytics query failed: ' . $error->getMessage());
        return null;
    }
}

function dashboardSiteMetrics(): ?array
{
    $cachePath = DASHBOARD_STORAGE . '/club-metrics-v1.json';
    if (is_file($cachePath) && filemtime($cachePath) > time() - 60) {
        $cached = json_decode((string) @file_get_contents($cachePath), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $pdo = dashboardDatabase();
    if ($pdo === null) {
        return null;
    }

    try {
        $metrics = [
            'members' => (int) $pdo->query('SELECT COUNT(*) FROM account_wtc')->fetchColumn(),
            'activeMembers' => (int) $pdo->query("SELECT COUNT(*) FROM account_wtc WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
            'upcomingSessions' => (int) $pdo->query('SELECT COUNT(*) FROM seances WHERE date_seance >= CURDATE()')->fetchColumn(),
            'sessionsThisMonth' => (int) $pdo->query("SELECT COUNT(*) FROM seances WHERE date_seance >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND date_seance < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)")->fetchColumn(),
            'enrolmentsThisMonth' => (int) $pdo->query("SELECT COUNT(*) FROM inscriptions_seances WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND created_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)")->fetchColumn(),
            'rankings' => (int) $pdo->query('SELECT COUNT(*) FROM ranking_records')->fetchColumn(),
            'reports' => (int) $pdo->query('SELECT COUNT(*) FROM signalements_wtc')->fetchColumn(),
        ];
        if (dashboardEnsureStorage()) {
            $temporaryPath = $cachePath . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($temporaryPath, (string) json_encode($metrics), LOCK_EX) !== false) {
                @chmod($temporaryPath, 0600);
                @rename($temporaryPath, $cachePath);
            }
        }
        return $metrics;
    } catch (Throwable $error) {
        error_log('[dashboard] Site metrics query failed: ' . $error->getMessage());
        return null;
    }
}