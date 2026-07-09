<?php
$currentId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if ($currentId <= 0) { header('Location: connexion.php'); exit; } $adminCheckStmt=$pdo->prepare('SELECT admin FROM
    account_wtc WHERE id = ?');
    $adminCheckStmt->execute([$currentId]);
    $isAdmin = (bool) $adminCheckStmt->fetchColumn();

    if (!$isAdmin) {
    $_SESSION['errors'] = ['Accès non autorisé.'];
    header('Location: index.php');
    exit;
    }

    $targetId = isset($_POST['target_id']) ? (int) $_POST['target_id'] : 0;
    if ($targetId <= 0 || $targetId===$currentId) { $_SESSION['errors']=['Impossible de modifier ce compte.'];
        header('Location: utilisateurs.php'); exit; } $currentValueStmt=$pdo->prepare('SELECT ban FROM account_wtc WHERE
        id = ?');
        $currentValueStmt->execute([$targetId]);
        $currentValue = $currentValueStmt->fetchColumn();

        if ($currentValue === false) {
        $_SESSION['errors'] = ['Utilisateur introuvable.'];
        header('Location: utilisateurs.php');
        exit;
        }

        $newValue = $currentValue ? 0 : 1;
        $updateStmt = $pdo->prepare('UPDATE account_wtc SET ban = ? WHERE id = ?');
        $updateStmt->execute([$newValue, $targetId]);

        $_SESSION['success'] = $newValue ? 'Le compte a été banni.' : 'Le compte a été débanni.';
        header('Location: utilisateurs.php');
        exit;
        }

        $banned = false;
        if ($currentId > 0) {
        $stmt = $pdo->prepare('SELECT ban FROM account_wtc WHERE id = ?');
        $stmt->execute([$currentId]);
        $banned = (bool) $stmt->fetchColumn();
        }

        if (!$banned) {
        header('Location: index.php');
        exit;
        }