<?php
declare(strict_types=1);

define('WTC_DISABLE_SQL_ACTION_AUDIT', true);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function analyticsRespond(int $status, array $payload = []): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function analyticsPath(mixed $value, bool $required = false): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $path = trim(explode('?', explode('#', $value, 2)[0], 2)[0]);
    if ($path === '' && !$required) {
        return null;
    }
    if (!str_starts_with($path, '/') || preg_match('/[\x00-\x1F\x7F]/', $path)) {
        return null;
    }
    return mb_substr($path, 0, 255, 'UTF-8');
}

function analyticsHost(mixed $value): ?string
{
    if (!is_string($value) || $value === '') {
        return null;
    }
    $host = strtolower(trim($value));
    if (strlen($host) > 190 || !preg_match('/^(?=.{1,190}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/', $host)) {
        return null;
    }
    return $host;
}

function analyticsDevice(string $userAgent): array
{
    $device = preg_match('/iPad|Tablet|PlayBook|Silk/i', $userAgent)
        ? 'tablet'
        : (preg_match('/Mobile|Android|iPhone|iPod/i', $userAgent) ? 'mobile' : 'desktop');
    $browser = match (true) {
        preg_match('/Edg\//i', $userAgent) === 1 => 'edge',
        preg_match('/OPR\//i', $userAgent) === 1 => 'opera',
        preg_match('/Firefox\//i', $userAgent) === 1 => 'firefox',
        preg_match('/Chrome\//i', $userAgent) === 1 => 'chrome',
        preg_match('/Safari\//i', $userAgent) === 1 => 'safari',
        default => 'other',
    };
    $operatingSystem = match (true) {
        preg_match('/iPhone|iPad|iPod/i', $userAgent) === 1 => 'ios',
        preg_match('/Android/i', $userAgent) === 1 => 'android',
        preg_match('/Windows/i', $userAgent) === 1 => 'windows',
        preg_match('/Macintosh|Mac OS/i', $userAgent) === 1 => 'macos',
        preg_match('/CrOS/i', $userAgent) === 1 => 'chromeos',
        preg_match('/Linux/i', $userAgent) === 1 => 'linux',
        default => 'other',
    };
    return [$device, $browser, $operatingSystem];
}

function analyticsNormalizeEvent(mixed $event): ?array
{
    if (!is_array($event)) {
        return null;
    }
    $type = $event['type'] ?? null;
    $page = analyticsPath($event['page'] ?? null, true);
    $allowedTypes = ['page_view', 'click', 'engagement', 'form_submit', 'scroll_depth', 'download', 'performance'];
    if (!in_array($type, $allowedTypes, true) || $page === null) {
        return null;
    }

    $targetType = null;
    $targetHost = null;
    $targetPath = null;
    if (in_array($type, ['click', 'download'], true)) {
        $targetType = $event['targetType'] ?? null;
        if (!in_array($targetType, ['link', 'button'], true)) {
            return null;
        }
        $targetHost = analyticsHost($event['targetHost'] ?? null);
        $targetPath = analyticsPath($event['targetPath'] ?? null);
    }

    $referrerHost = $type === 'page_view' ? analyticsHost($event['referrerHost'] ?? null) : null;
    $label = null;
    if (isset($event['label']) && is_string($event['label'])) {
        $label = trim(preg_replace('/[^\pL\pN _.,:&()\/-]/u', '', $event['label']) ?? '');
        $label = $label === '' ? null : mb_substr($label, 0, 80);
    }

    $metricName = null;
    $metricValue = null;
    if ($type === 'performance') {
        $metricName = $event['metricName'] ?? null;
        $metricValue = filter_var($event['metricValue'] ?? null, FILTER_VALIDATE_FLOAT);
        if (!in_array($metricName, ['page_load_ms', 'fcp_ms', 'lcp_ms', 'inp_ms', 'cls'], true)
            || $metricValue === false
            || $metricValue < 0
            || $metricValue > ($metricName === 'cls' ? 10 : 60000)
        ) {
            return null;
        }
    }

    $scrollDepth = null;
    if ($type === 'scroll_depth') {
        $scrollDepth = filter_var($event['scrollDepth'] ?? null, FILTER_VALIDATE_INT);
        if (!in_array($scrollDepth, [25, 50, 75, 100], true)) {
            return null;
        }
    }

    return [
        'type' => $type,
        'page' => $page,
        'targetType' => $targetType,
        'targetHost' => $targetHost,
        'targetPath' => $targetPath,
        'referrerHost' => $referrerHost,
        'label' => $label,
        'metricName' => $metricName,
        'metricValue' => $metricValue,
        'scrollDepth' => $scrollDepth,
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    analyticsRespond(405, ['error' => 'Méthode non autorisée.']);
}

$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$requestHost = strtolower((string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
$originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
if ($origin === '' || !in_array((string) parse_url($origin, PHP_URL_SCHEME), ['http', 'https'], true) || $requestHost === '' || !hash_equals($requestHost, $originHost)) {
    analyticsRespond(403, ['error' => 'Origine non autorisée.']);
}

$rawBody = file_get_contents('php://input', false, null, 0, 8193);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192 || !is_string($rawBody) || strlen($rawBody) > 8192) {
    analyticsRespond(413, ['error' => 'Événement trop volumineux.']);
}
$payload = json_decode($rawBody, true);
if (!is_array($payload) || ($payload['consent'] ?? '') !== 'accepted') {
    analyticsRespond(400, ['error' => 'Consentement requis.']);
}

$sessionId = $payload['sessionId'] ?? null;
if (!is_string($sessionId) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $sessionId)) {
    analyticsRespond(400, ['error' => 'Événement invalide.']);
}

$events = $payload['events'] ?? null;
if ($events === null && isset($payload['type'])) {
    $events = [$payload];
}
if (!is_array($events) || $events === [] || count($events) > 12 || array_is_list($events) !== true) {
    analyticsRespond(400, ['error' => 'Lot d’événements invalide.']);
}
$normalizedEvents = [];
foreach ($events as $event) {
    $normalized = analyticsNormalizeEvent($event);
    if ($normalized === null) {
        analyticsRespond(400, ['error' => 'Un événement du lot est invalide.']);
    }
    $normalizedEvents[] = $normalized;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$analyticsAccountId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
$analyticsAccountId = $analyticsAccountId !== false && $analyticsAccountId !== null && $analyticsAccountId > 0
    ? $analyticsAccountId
    : null;
session_write_close();

[$device, $browser, $operatingSystem] = analyticsDevice((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
$viewportWidth = filter_var($payload['viewportWidth'] ?? null, FILTER_VALIDATE_INT);
$viewport = $viewportWidth === false || $viewportWidth === null
    ? 'unknown'
    : ($viewportWidth < 600 ? 'small' : ($viewportWidth < 1024 ? 'medium' : 'large'));
$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/vendor/autoload.php';
try {
    if (is_file($projectRoot . '/.env')) {
        Dotenv\Dotenv::createUnsafeImmutable($projectRoot)->safeLoad();
    }
} catch (Throwable $error) {
    error_log('[analytics] Environment loading failed.');
}
$env = static function (string $key, string $default = ''): string {
    $value = getenv($key);
    if (is_string($value) && $value !== '') return $value;
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    return is_string($value) ? $value : $default;
};
$dbHost = $env('DB_HOST', '127.0.0.1');
$dbPort = $env('DB_PORT', '3306');
$dbName = $env('DB_DATABASE');
$dbUser = $env('DB_USERNAME');
if ($dbName === '' || $dbUser === '' || !ctype_digit($dbPort)
    || !preg_match('/^[A-Za-z0-9_.:-]+$/', $dbHost)
    || !preg_match('/^[A-Za-z0-9_]+$/', $dbName)
) {
    analyticsRespond(503, ['error' => 'Stockage des statistiques indisponible.']);
}

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $env('DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 3]
    );
    $sessionHash = hash('sha256', strtolower($sessionId));
    $quota = $pdo->prepare('SELECT COUNT(*) FROM analytics_events WHERE session_hash = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    $quota->execute([$sessionHash]);
    if ((int) $quota->fetchColumn() + count($normalizedEvents) > 240) {
        analyticsRespond(429, ['error' => 'Limite d’événements de session atteinte.']);
    }

    $values = [];
    $parameters = [];
    foreach ($normalizedEvents as $event) {
        $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        array_push(
            $parameters,
            $sessionHash,
            $analyticsAccountId,
            $event['type'],
            $event['page'],
            $event['targetType'],
            $event['targetHost'],
            $event['targetPath'],
            $event['referrerHost'],
            $event['label'],
            $event['metricName'],
            $event['metricValue'],
            $event['scrollDepth'],
            $device,
            $browser,
            $operatingSystem,
            $viewport,
        );
    }
    $pdo->beginTransaction();
    $insert = $pdo->prepare(
        'INSERT INTO analytics_events
            (session_hash, account_id, event_type, page_path, target_type, target_host, target_path, referrer_host, interaction_label, metric_name, metric_value, scroll_depth, device_category, browser_category, operating_system, viewport_category)
         VALUES ' . implode(', ', $values)
    );
    $insert->execute($parameters);
    $pdo->commit();
} catch (Throwable $error) {
    if (($pdo ?? null) instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[analytics] Event write failed: ' . $error->getMessage());
    analyticsRespond(503, ['error' => 'Enregistrement des statistiques indisponible.']);
}

analyticsRespond(202, ['accepted' => true, 'stored' => count($normalizedEvents)]);