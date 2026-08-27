<?php
require_once __DIR__ . '/../general/session-config.php';
require_once __DIR__ . '/../general/db.php';
require_once __DIR__ . '/../general/persistent-auth.php';
require_once __DIR__ . '/../security/LoginAttemptThrottler.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['errors'] = ["Le service est temporairement indisponible. Réessaie plus tard."];
    header('Location: ../../connexion.php');
    exit;
}

$clientIp = IpAddressValidator::getClientIp();

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
    try {
        if (!LoginAttemptThrottler::isLoginAllowed($email, $clientIp)) {
            $errors[] = "Trop de tentatives de connexion. Réessaie plus tard.";
        } else {
            $stmt = $pdo->prepare('SELECT * FROM account_wtc WHERE email = :email');
            $stmt->execute(['email' => $email]);
            $account = $stmt->fetch();

            $loginFailed = false;
            if (!$account || !password_verify($password, $account['password'])) {
                $loginFailed = true;
                $errors[] = "Identifiants incorrects.";
                LoginAttemptThrottler::recordFailedAttempt($email, $clientIp);
            } elseif ((int) $account['ban'] === 1) {
                $errors[] = "Ce compte a été banni. Contacte un administrateur du club.";
                LoginAttemptThrottler::recordFailedAttempt($email, $clientIp);
            } elseif ((int) $account['email_verified'] !== 1) {
                $errors[] = "Confirme ton adresse email avant de te connecter. Vérifie ta boîte mail.";
                LoginAttemptThrottler::recordFailedAttempt($email, $clientIp);
            }
        }
    } catch (Exception $e) {
        error_log("Erreur throttling login: " . $e->getMessage());
        $errors[] = "Une erreur est survenue. Réessaie plus tard.";
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
// Record successful login
try {
    LoginAttemptThrottler::recordSuccessfulLogin($email);
} catch (Exception $e) {
    error_log("Erreur enregistrement succès login: " . $e->getMessage());
}

header('Location: ../../index.php');
exit;
