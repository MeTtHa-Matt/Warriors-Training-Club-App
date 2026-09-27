<?php
define('WTC_DISABLE_SQL_ACTION_AUDIT', true);
require_once __DIR__ . '/../includes/general/session-config.php';
require_once __DIR__ . '/../includes/general/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0 || !($pdo instanceof PDO)) {
    $respond(['error' => 'Ta session a expiré. Reconnecte-toi puis recharge cette page.', 'code' => 'session_expired'], 401);
}

try {
    $adminQuery = $pdo->prepare('SELECT admin, ban FROM account_wtc WHERE id = ?');
    $adminQuery->execute([$userId]);
    $admin = $adminQuery->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log('[sql-actions] admin check failed: ' . $error->getMessage());
    $respond(['error' => 'La base de données ne répond pas. Réessaie dans quelques instants.', 'code' => 'database_unavailable'], 503);
}

if (!$admin || (int) $admin['admin'] !== 1 || (int) $admin['ban'] === 1) {
    $respond(['error' => 'Ton compte ne dispose pas des droits administrateur.', 'code' => 'admin_required'], 403);
}

global $auditDisabled, $sqlActionAuditReady;
if (!empty($auditDisabled)) {
    $respond(['error' => 'La collecte SQL est désactivée par la configuration du serveur.', 'code' => 'audit_disabled'], 503);
}
if (empty($sqlActionAuditReady)) {
    $respond(['error' => 'La table du journal SQL est inaccessible. Vérifie les droits SQL de création de table.', 'code' => 'audit_storage_unavailable'], 503);
}

try {
    $cursor = filter_var($_GET['after_id'] ?? null, FILTER_VALIDATE_INT);
    if ($cursor === false || $cursor === null || $cursor < 0) {
        $items = [];
        $query = $pdo->prepare(
            "SELECT l.id, l.created_at, l.actor_id, l.query_type, l.statement, l.status,
                    CASE
                        WHEN l.actor_id IS NULL THEN 'Visiteur'
                        ELSE COALESCE(NULLIF(TRIM(CONCAT_WS(' ', a.firstname, a.lastname)), ''), CONCAT('Compte #', l.actor_id))
                    END AS actor_name
             FROM sql_action_logs l
             LEFT JOIN account_wtc a ON a.id = l.actor_id
             WHERE l.query_type = ? ORDER BY l.id DESC LIMIT 150"
        );
        foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'OTHER'] as $type) {
            $query->execute([$type]);
            $items = array_merge($items, array_reverse($query->fetchAll(PDO::FETCH_ASSOC)));
        }
        usort($items, static fn(array $left, array $right): int => (int) $left['id'] <=> (int) $right['id']);
    } else {
        $query = $pdo->prepare(
            "SELECT l.id, l.created_at, l.actor_id, l.query_type, l.statement, l.status,
                    CASE
                        WHEN l.actor_id IS NULL THEN 'Visiteur'
                        ELSE COALESCE(NULLIF(TRIM(CONCAT_WS(' ', a.firstname, a.lastname)), ''), CONCAT('Compte #', l.actor_id))
                    END AS actor_name
             FROM sql_action_logs l
             LEFT JOIN account_wtc a ON a.id = l.actor_id
             WHERE l.id > ? ORDER BY l.id ASC LIMIT 500"
        );
        $query->execute([$cursor]);
        $items = $query->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($items as &$item) {
        $item['id'] = (int) $item['id'];
        $item['actor_id'] = $item['actor_id'] === null ? null : (int) $item['actor_id'];
    }
    unset($item);
    $respond(['items' => $items]);
} catch (Throwable $error) {
    error_log('[sql-actions] ' . $error->getMessage());
    $respond(['error' => 'Impossible de lire le journal SQL. Vérifie que la table sql_action_logs existe et que le compte SQL peut la lire.', 'code' => 'audit_read_failed'], 503);
}