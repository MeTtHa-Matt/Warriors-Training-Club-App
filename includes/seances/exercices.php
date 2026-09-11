<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../general/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$storagePath = __DIR__ . '/../../data/exercices.json';

function readExercises(string $storagePath): array
{
    if (!is_file($storagePath)) {
        return [];
    }

    $data = json_decode(file_get_contents($storagePath) ?: '{}', true);
    return is_array($data) ? $data : [];
}

function writeExercises(string $storagePath, array $data): bool
{
    $handle = fopen($storagePath, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        return false;
    }

    ftruncate($handle, 0);
    rewind($handle);
    $written = fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $written !== false;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        $stmt = $pdo->query('SELECT id, date_seance, heure_debut, heure_fin, type_seance, coach FROM seances ORDER BY date_seance DESC, heure_debut DESC');
        echo json_encode(['seances' => $stmt->fetchAll()]);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, date_seance, heure_debut, heure_fin, type_seance, coach FROM seances WHERE id = :id');
    $stmt->execute(['id' => $id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }

    $data = readExercises($storagePath);
    echo json_encode([
        'id' => $id,
        'content' => isset($data[(string) $id]) ? (string) $data[(string) $id]['content'] : '',
        'has_exercices' => isset($data[(string) $id]) && trim((string) $data[(string) $id]['content']) !== '',
    ]);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

if ((int) ($_SESSION['gerer_seances'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || !is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'csrf_failed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int) ($input['id'] ?? 0);
$content = trim((string) ($input['content'] ?? ''));

if ($id <= 0 || mb_strlen($content) > 100000) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_input']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM seances WHERE id = :id');
$stmt->execute(['id' => $id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

$data = readExercises($storagePath);
$key = (string) $id;
if ($content === '') {
    unset($data[$key]);
} else {
    $data[$key] = [
        'content' => $content,
        'updated_at' => date(DATE_ATOM),
        'updated_by' => (int) $_SESSION['user_id'],
    ];
}

if (!writeExercises($storagePath, $data)) {
    http_response_code(500);
    echo json_encode(['error' => 'write_failed']);
    exit;
}

echo json_encode(['success' => true, 'has_exercices' => $content !== '']);
