<?php
require_once __DIR__ . '/session-config.php';
require_once __DIR__ . '/verifications.php';
require_once __DIR__ . '/db.php';

if (empty($_SESSION['user_id']) || (int) ($_SESSION['admin'] ?? 0) !== 1) {
    header('Location: index.php');
    exit;
}

$reports = $pdo->query('SELECT id, email, message, created_at FROM dashboard_reports ORDER BY created_at DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Warriors Training Club - Signalements';
