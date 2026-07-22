<?php
require_once __DIR__ . '/session-config.php';
require_once __DIR__ . '/verifications.php';
require_once __DIR__ . '/db.php';

$currentId = $_SESSION['user_id'] ?? 0;
if ($currentId <= 0 || (int) ($_SESSION['admin'] ?? 0) !== 1) {
    header('Location: index.php');
    exit;
}

$errors = $_SESSION['errors'] ?? [];
$success = $_SESSION['success'] ?? null;
unset($_SESSION['errors'], $_SESSION['success']);

$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM account_wtc')->fetchColumn();
$totalAdmins = (int) $pdo->query('SELECT COUNT(*) FROM account_wtc WHERE admin = 1')->fetchColumn();
$totalMaintenanceUsers = (int) $pdo->query('SELECT COUNT(*) FROM account_wtc WHERE maintenance = 1')->fetchColumn();
$totalSessions = (int) $pdo->query('SELECT COUNT(*) FROM seances')->fetchColumn();
$upcomingSessions = (int) $pdo->query('SELECT COUNT(*) FROM seances WHERE date_seance >= CURDATE()')->fetchColumn();
$totalInscriptions = (int) $pdo->query('SELECT COUNT(*) FROM inscriptions_seances')->fetchColumn();
$totalReports = (int) $pdo->query('SELECT COUNT(*) FROM signalements_wtc')->fetchColumn();
$totalEmailsOptOut = (int) $pdo->query('SELECT COUNT(*) FROM account_wtc WHERE accept_email = 0')->fetchColumn();

$pageTitle = 'Warriors Training Club - Administration';

$adminActions = [
    [
        'url' => 'utilisateurs.php',
        'icon' => 'bi bi-people',
        'label' => 'Gestion des utilisateurs',
        'description' => 'Voir et modifier les droits, bannir ou rendre admin.',
    ],
    [
        'url' => 'db-audit.php',
        'icon' => 'bi bi-journal-text',
        'label' => 'Audit base de données',
        'description' => 'Consulter le journal JSON de toutes les actions SQL effectuées.',
    ],
    [
        'url' => 'envoyer-mail.php',
        'icon' => 'bi bi-envelope',
        'label' => 'Envoyer un mail',
        'description' => 'Rédiger et envoyer un message à l’ensemble des membres.',
    ],
    [
        'url' => 'liens-index.php',
        'icon' => 'bi bi-link-45deg',
        'label' => 'Modifier les liens',
        'description' => 'Mettre à jour les urls affichées sur la page d’accueil.',
    ],
];
