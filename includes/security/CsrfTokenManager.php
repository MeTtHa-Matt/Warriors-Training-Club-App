<?php
/**
 * Gestionnaire de jetons CSRF
 * Protège contre les attaques Cross-Site Request Forgery
 */
class CsrfTokenManager {
    const TOKEN_LENGTH = 32;
    const TOKEN_NAME = 'csrf_token';
    const TOKEN_LIFETIME = 3600; // 1 heure

    /**
     * Génère un nouveau jeton CSRF
     */
    public static function generateToken() {
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }

        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION['csrf_tokens'][$token] = time();
        self::cleanup();

        return $token;
    }

    /**
     * Valide un jeton CSRF
     */
    public static function validateToken($token) {
        if (!isset($_SESSION['csrf_tokens'][$token])) {
            return false;
        }

        $tokenTime = $_SESSION['csrf_tokens'][$token];

        if (time() - $tokenTime > self::TOKEN_LIFETIME) {
            unset($_SESSION['csrf_tokens'][$token]);
            return false;
        }

        unset($_SESSION['csrf_tokens'][$token]);
        return true;
    }

    /**
     * Nettoie les anciens jetons expirés
     */
    private static function cleanup() {
        if (!isset($_SESSION['csrf_tokens'])) return;

        $now = time();
        foreach ($_SESSION['csrf_tokens'] as $token => $time) {
            if ($now - $time > self::TOKEN_LIFETIME) {
                unset($_SESSION['csrf_tokens'][$token]);
            }
        }
    }
}
?>
