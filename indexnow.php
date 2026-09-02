<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

const INDEXNOW_ENDPOINT = 'https://api.indexnow.org/indexnow';
const INDEXNOW_KEY_FILE = '44e3354927004d74a443c86df0478675.txt';

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function requestUrls(): array
{
    if (PHP_SAPI === 'cli') {
        global $argv;
        return array_slice($argv, 1);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['error' => 'Méthode autorisée : POST.'], 405);
    }

    $expectedToken = trim((string) getenv('INDEXNOW_HTTP_TOKEN'));
    $providedToken = $_SERVER['HTTP_X_INDEXNOW_TOKEN'] ?? '';
    if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
        respond(['error' => 'Accès non autorisé.'], 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $urls = $input['urlList'] ?? $input['urls'] ?? $input['url'] ?? [];
    return is_array($urls) ? $urls : [$urls];
}

function siteUrl(): string
{
    $configuredUrl = trim((string) (getenv('INDEXNOW_SITE_URL') ?: getenv('APP_BASE_URL')));
    return rtrim($configuredUrl ?: 'https://warriors-training-club.judo-club-mormant.fr', '/');
}

$key = trim((string) getenv('INDEXNOW_KEY'));
if ($key === '' && is_readable(__DIR__ . '/' . INDEXNOW_KEY_FILE)) {
    $key = trim((string) file_get_contents(__DIR__ . '/' . INDEXNOW_KEY_FILE));
}

if (!preg_match('/^[a-f0-9]{32}$/i', $key)) {
    respond(['error' => 'Clé IndexNow absente ou invalide.'], 500);
}

$baseUrl = siteUrl();
$host = parse_url($baseUrl, PHP_URL_HOST);
if (!is_string($host) || $host === '') {
    respond(['error' => 'INDEXNOW_SITE_URL doit être une URL valide.'], 500);
}

$urls = [];
foreach (requestUrls() as $url) {
    $url = trim((string) $url);
    if ($url === '') {
        continue;
    }

    $parsedUrl = filter_var($url, FILTER_VALIDATE_URL) ? parse_url($url) : null;
    if (!is_array($parsedUrl) || ($parsedUrl['host'] ?? '') !== $host || !in_array($parsedUrl['scheme'] ?? '', ['http', 'https'], true)) {
        respond(['error' => 'Chaque URL doit appartenir à ' . $host . '.'], 400);
    }
    $urls[] = $url;
}

$urls = array_values(array_unique($urls));
if ($urls === [] || count($urls) > 10_000) {
    respond(['error' => 'Fournissez entre 1 et 10 000 URL(s).'], 400);
}

$payload = json_encode([
    'host' => $host,
    'key' => $key,
    'keyLocation' => $baseUrl . '/' . INDEXNOW_KEY_FILE,
    'urlList' => $urls,
], JSON_UNESCAPED_SLASHES);

$curl = curl_init(INDEXNOW_ENDPOINT);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json; charset=utf-8',
        'Content-Length: ' . strlen((string) $payload),
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
]);

$responseBody = curl_exec($curl);
$curlError = curl_error($curl);
$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
curl_close($curl);

if ($responseBody === false) {
    respond(['error' => 'Échec de la requête IndexNow.', 'details' => $curlError], 502);
}

respond([
    'success' => $status >= 200 && $status < 300,
    'indexnow_status' => $status,
    'submitted' => count($urls),
    'response' => $responseBody,
], $status >= 200 && $status < 300 ? 200 : 502);