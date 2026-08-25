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
    }
}

if (!empty($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    if (!empty($pdo)) {
        $stmt = $pdo->prepare('SELECT admin, ban, reglement_accepte FROM account_wtc WHERE id = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            if ((int) $result['ban'] === 1) {
                $tokenManager->clear();
                session_destroy();
                app_redirect('ban.php');
            }

            $isAdmin = ((int) $result['admin'] === 1);
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
    } else {
        // Without DB available, fall back to session values where possible
        $isAdmin = (int) ($_SESSION['admin'] ?? 0);
    }
}

if (!isset($_SESSION['last_token_cleanup']) || time() - $_SESSION['last_token_cleanup'] > 3600) {
    $tokenManager->cleanupExpired();
    $_SESSION['last_token_cleanup'] = time();
}

// If maintenance marker exists, prevent anonymous users from accessing pages
// Global DB-driven maintenance check: if any account has maintenance=1, redirect
// users (anonymous or non-admin logged-in) to maintenance.php, except connexion.php
$currentPage = basename($_SERVER['PHP_SELF']);
if ($currentPage !== 'connexion.php' && $currentPage !== 'maintenance.php') {
    if (!empty($pdo)) {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM account_wtc WHERE maintenance = 1')->fetchColumn();
        if ($count > 0) {
            $isAdmin = (int) ($_SESSION['admin'] ?? 0);
            if (empty($_SESSION['user_id']) || $isAdmin === 0) {
                app_redirect('maintenance.php');
            }
        }
    }
}