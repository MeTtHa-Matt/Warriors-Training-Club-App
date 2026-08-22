<?php
require_once __DIR__ . '/../general/session-config.php';
require_once __DIR__ . '/../general/db.php';
require_once __DIR__ . '/../general/persistent-auth.php';

// Login attempt throttle settings
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_BLOCK_SECONDS = 300; // 5 minutes
$loginAttemptsPath = __DIR__ . '/../../data/login_attempts.json';

function load_login_attempts(string $path): array {
    if (!is_file($path)) return [];
    $raw = @file_get_contents($path);
    if ($raw === false) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function save_login_attempts(string $path, array $data): void {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function get_login_state(string $email, string $path): array {
    $all = load_login_attempts($path);
    $key = strtolower(trim($email));
    return $all[$key] ?? ['count' => 0, 'blocked_until' => 0];
}

function set_login_state(string $email, array $state, string $path): void {
    $all = load_login_attempts($path);
    $key = strtolower(trim($email));
    $all[$key] = $state;
    save_login_attempts($path, $all);
}

function clear_login_state(string $email, string $path): void {
    $all = load_login_attempts($path);
    $key = strtolower(trim($email));
    if (isset($all[$key])) {
        unset($all[$key]);
        save_login_attempts($path, $all);
    }
}

$cleanupStmt = $pdo->prepare(
    'DELETE FROM account_wtc
     WHERE email_verified = 0
       AND verification_token IS NOT NULL
       AND verification_token_expires IS NOT NULL
       AND verification_token_expires < NOW()'
);
$cleanupStmt->execute();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../connexion.php');
    exit;
}

$errors = [];

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Adresse email invalide.";
}
if ($password === '') {
    $errors[] = "Merci de renseigner ton mot de passe.";
}

// Check for temporary block before querying the DB
$account = null;
if (empty($errors)) {
    $state = get_login_state($email, $loginAttemptsPath);
    $now = time();
    if (!empty($state['blocked_until']) && (int)$state['blocked_until'] > $now) {
        $wait = (int)$state['blocked_until'] - $now;
        $when = date('H:i', $state['blocked_until']);
        $errors[] = "Trop de tentatives de connexion. Compte bloqué pendant '" . ceil($wait/60) . "' minute(s). Réessaie après $when.";
    } else {
        $stmt = $pdo->prepare('SELECT * FROM account_wtc WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $account = $stmt->fetch();

        $loginFailed = false;
        if (!$account || !password_verify($password, $account['password'])) {
            $loginFailed = true;
            $errors[] = "Identifiants incorrects.";
        } elseif ((int) $account['ban'] === 1) {
            $errors[] = "Ce compte a été banni. Contacte un administrateur du club.";
        } elseif ((int) $account['email_verified'] !== 1) {
            $errors[] = "Confirme ton adresse email avant de te connecter. Vérifie ta boîte mail.";
        }

        // Record failure count if login failed
        if (!empty($loginFailed)) {
            $s = $state;
            $s['count'] = (int) ($s['count'] ?? 0) + 1;
            if ($s['count'] >= LOGIN_MAX_ATTEMPTS) {
                $s['blocked_until'] = time() + LOGIN_BLOCK_SECONDS;
                // reset count to avoid growing number
                $s['count'] = 0;
                $when = date('H:i', $s['blocked_until']);
                // overwrite errors with block message
                $errors = ["Trop de tentatives de connexion. Compte bloqué pendant 5 minutes. Réessaie après $when."];
            }
            set_login_state($email, $s, $loginAttemptsPath);
        }
    }
}

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = ['email' => $email];
    header('Location: ../../connexion.php');
    exit;
}

session_regenerate_id(true);

$_SESSION['user_id'] = $account['id'];
$_SESSION['firstname'] = $account['firstname'];
$_SESSION['lastname'] = $account['lastname'];
$_SESSION['email'] = $account['email'];
$_SESSION['pdp'] = $account['pdp'];
$_SESSION['admin'] = (int) $account['admin'];
$_SESSION['gerer_seances'] = (int) $account['gerer_seances'];
$_SESSION['ban'] = (int) $account['ban'];
// Store session creation timestamp for timeout calculations
$_SESSION['created_at'] = time();

$tokenManager = new PersistentToken($pdo);
$tokenManager->create($account['id']);
// On successful login clear recorded failures
clear_login_state($email, $loginAttemptsPath);

header('Location: ../../index.php');
exit;
