<?php
require_once __DIR__ . '/session-config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/persistent-auth.php';

/**
 * Redirect helper that preserves the application subfolder when present.
 * Accepts a relative path like 'reglement-accept.php' or 'folder/page.php'.
 */
function app_redirect(string $relativePath)
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    if ($base === '' || $base === '.') {
        $url = '/' . ltrim($relativePath, '/');
    } else {
        $url = $base . '/' . ltrim($relativePath, '/');
    }
    header('Location: ' . $url);
    exit;
}

$tokenManager = new PersistentToken($pdo);

if (empty($_SESSION['user_id'])) {
    $userData = $tokenManager->validate();

    if ($userData) {
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['firstname'] = $userData['firstname'];
        $_SESSION['lastname'] = $userData['lastname'];
        $_SESSION['email'] = $userData['email'];
        $_SESSION['pdp'] = $userData['pdp'];
        $_SESSION['admin'] = (int) $userData['admin'];
        $_SESSION['gerer_seances'] = (int) $userData['gerer_seances'];
        $_SESSION['ban'] = (int) $userData['ban'];
        $_SESSION['maintenance'] = (int) $userData['maintenance'];
    }
}

if (!empty($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    $stmt = $pdo->prepare('SELECT admin, maintenance, ban, reglement_accepte FROM account_wtc WHERE id = ?');
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        if ((int) $result['ban'] === 1) {
            $tokenManager->clear();
            session_destroy();
            app_redirect('ban.php');
        }

        if ((int) $result['admin'] === 0 && (int) $result['maintenance'] === 1) {
            app_redirect('maintenance.php');
        }

        // store reglement status in session for quick checks
        $_SESSION['reglement_accepte'] = (int) $result['reglement_accepte'];

        // If the user hasn't accepted the règlement, redirect them to the acceptance page
        // Allow the user to access certain pages without being redirected to avoid loops
        $allowedPages = [
            'reglement-accept.php',
            'deconnexion_process.php',
            'connexion.php',
            'connexion_process.php',
            'inscription.php',
            'inscription_process.php',
            'mot-de-passe-oublie.php',
            'reinitialiser-mot-de-passe.php',
            'ban.php',
            'maintenance.php',
            'offline.html'
        ];

        $currentPage = basename($_SERVER['PHP_SELF']);

        if ($_SESSION['reglement_accepte'] === 0 && !in_array($currentPage, $allowedPages, true)) {
            app_redirect('reglement-accept.php');
        }
    }
}

if (!isset($_SESSION['last_token_cleanup']) || time() - $_SESSION['last_token_cleanup'] > 3600) {
    $tokenManager->cleanupExpired();
    $_SESSION['last_token_cleanup'] = time();
}