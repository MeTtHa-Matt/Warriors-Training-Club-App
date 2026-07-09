<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../general/db.php';
require_once __DIR__ . '/template_helpers.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
if ((int) ($_SESSION['gerer_seances'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

ensureSeanceTemplateSchema($pdo);

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT * FROM seance_templates WHERE created_by = ? ORDER BY created_at DESC');
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['templates' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($method === 'POST') {
    $id = (int) ($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    $sessions = is_array($input['sessions'] ?? null) ? $input['sessions'] : [];

    if ($name === '') {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_input', 'messages' => ['Le nom du template est obligatoire.']]);
        exit;
    }
    if (empty($sessions)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_input', 'messages' => ['Ajoute au moins une séance au template avant de l’enregistrer.']]);
        exit;
    }

    if ($id > 0) {
        $checkStmt = $pdo->prepare('SELECT id FROM seance_templates WHERE id = ? AND created_by = ? LIMIT 1');
        $checkStmt->execute([$id, $_SESSION['user_id']]);
        if (!$checkStmt->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['error' => 'template_not_found']);
            exit;
        }

        $updateStmt = $pdo->prepare(
            'UPDATE seance_templates SET name = :name, sessions = :sessions 
             WHERE id = :id AND created_by = :created_by'
        );
        $updateStmt->execute([
            'name' => $name,
            'sessions' => json_encode($sessions, JSON_UNESCAPED_UNICODE),
            'id' => $id,
            'created_by' => $_SESSION['user_id'],
        ]);

        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO seance_templates (name, sessions, created_by)
         VALUES (:name, :sessions, :created_by)'
    );
    $stmt->execute([
        'name' => $name,
        'sessions' => json_encode($sessions, JSON_UNESCAPED_UNICODE),
        'created_by' => $_SESSION['user_id'],
    ]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_input']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM seance_templates WHERE id = ? AND created_by = ?');
    $stmt->execute([$id, $_SESSION['user_id']]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
