<?php
require_once __DIR__ . '/includes/general/session-config.php';
require_once __DIR__ . '/includes/general/db.php';

$currentId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($currentId <= 0) {
        header('Location: connexion.php');
        exit;
    }

    $adminCheckStmt = $pdo->prepare('SELECT admin FROM account_wtc WHERE id = ?');
    $adminCheckStmt->execute([$currentId]);
    $isAdmin = (bool) $adminCheckStmt->fetchColumn();

    if (!$isAdmin) {
        $_SESSION['errors'] = ['Accès non autorisé.'];
        header('Location: index.php');
        exit;
    }

    $enable = isset($_POST['enable']) ? (int) $_POST['enable'] : 0;
    $enable = $enable ? 1 : 0;

    $pdo->prepare('UPDATE account_wtc SET maintenance = ?')->execute([$enable]);

    $_SESSION['success'] = $enable ? 'La maintenance a été activée.' : 'La maintenance a été désactivée.';
    header('Location: utilisateurs.php');
    exit;
}

$maintenanceEnabled = (bool) $pdo->query('SELECT MAX(maintenance) FROM account_wtc')->fetchColumn();

$isAdmin = false;
if ($currentId > 0) {
    $adminCheckStmt = $pdo->prepare('SELECT admin FROM account_wtc WHERE id = ?');
    $adminCheckStmt->execute([$currentId]);
    $isAdmin = (bool) $adminCheckStmt->fetchColumn();
}

if ($maintenanceEnabled && !$isAdmin) {
    ?><!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Maintenance</title>
        <link rel="stylesheet" href="css/style.css?v=202607051">
    </head>
    <body>
    <section class="section auth-section">
        <div class="container">
            <div class="auth-wrapper text-center">
                <h1>Site en maintenance</h1>
                <p>Le site est temporairement indisponible pour maintenance. Merci de revenir plus tard.</p>
            </div>
        </div>
    </section>
    <?php require 'includes/general/footer.php'; ?>
    </body>
    </html>
    <?php
    exit;
}

header('Location: index.php');
exit;
