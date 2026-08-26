<?php
require_once __DIR__ . '/../security/RateLimiter.php';

$pageTitle = "Warriors Training Club - Mot de passe oublié";

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = trim((string)($_POST['email'] ?? ''));

        if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Invalid CSRF token');
        }
        RateLimiter::checkRateLimit('password-reset-email:' . strtolower($email), 3, 3600);
        RateLimiter::checkRateLimit('password-reset-ip:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 10, 3600);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Merci d’entrer une adresse email valide.";
        } else {
            $stmt = $pdo->prepare('SELECT id, firstname, email FROM account_wtc WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$account) {
                $success = "Si un compte existe pour cette adresse, un email de réinitialisation a été envoyé.";
            } else {
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                $updateStmt = $pdo->prepare('UPDATE account_wtc SET password_reset_token = ?, password_reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?');
                $updateStmt->execute([$token, $account['id']]);

                $baseUrl = getApplicationBaseUrl();
                $link = $baseUrl . '/reinitialiser-mot-de-passe.php?token=' . urlencode($token);
                $mailResult = sendPasswordResetEmail($account['email'], $account['firstname'], $link);

                if ($mailResult['success']) {
                    $success = "Si un compte existe pour cette adresse, un email de réinitialisation a été envoyé.";
                } else {
                    $errors[] = "Impossible d’envoyer l’email pour le moment. Merci de réessayer plus tard.";
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[mdp_oublie] exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $errors[] = "Une erreur est survenue. Merci de réessayer plus tard.";
    }
}