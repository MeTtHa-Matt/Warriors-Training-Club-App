<?php
/**
 * Gestionnaire sécurisé de jetons d'authentification persistante
 * Implémente le hachage et la rotation des jetons
 */
class SecurePersistentAuthToken {
    private const TOKEN_LENGTH = 32;
    private const TOKEN_VALIDITY_DAYS = 30;
    private const TOKEN_ROTATION_INTERVAL = 604800; // 7 jours

    /**
     * Génère un token sécurisé avec hachage
     */
    public static function generateToken($userId, $pdo) {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + (self::TOKEN_VALIDITY_DAYS * 24 * 3600));

        $stmt = $pdo->prepare(
            "INSERT INTO persistent_tokens (account_id, token, ip_address, user_agent, expires_at)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId,
            $tokenHash,
            self::getClientIp(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $expiresAt
        ]);

        return $token;
    }

    /**
     * Valide un token et effectue la rotation si nécessaire
     */
    public static function validateAndRotateToken($token, $userId, $pdo) {
        if (empty($token)) {
            return false;
        }

        $tokenHash = hash('sha256', $token);
        $currentIp = self::getClientIp();
        $currentUserAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $stmt = $pdo->prepare(
              "SELECT id, account_id, ip_address, user_agent, last_used
               FROM persistent_tokens
               WHERE token = ? AND expires_at > NOW()"
        );
           $stmt->execute([$tokenHash]);
        $result = $stmt->fetch();

        if (!$result) {
            return false;
        }

        if ($result['user_agent'] !== $currentUserAgent || (int) $result['account_id'] !== (int) $userId && $userId !== 0) {
            error_log("Tentative de détournement de token pour user $userId");
            return false;
        }

        // Effectuer la rotation tous les 7 jours
        $lastUsed = $result['last_used'] ? strtotime($result['last_used']) : 0;
        if ($lastUsed > 0 && time() - $lastUsed > self::TOKEN_ROTATION_INTERVAL) {
            $newToken = bin2hex(random_bytes(self::TOKEN_LENGTH));
            $newTokenHash = hash('sha256', $newToken);

            $updateStmt = $pdo->prepare(
                 "UPDATE persistent_tokens
                  SET token = ?, last_used = NOW()
                 WHERE id = ?"
            );
              $updateStmt->execute([$newTokenHash, $result['id']]);

            return $newToken;
        }

        $updateStmt = $pdo->prepare('UPDATE persistent_tokens SET last_used = NOW() WHERE id = ?');
        $updateStmt->execute([$result['id']]);
        return $token;
    }

    /**
     * Invalide un token (logout)
     */
    public static function invalidateToken($userId, $pdo) {
        $stmt = $pdo->prepare("DELETE FROM persistent_tokens WHERE account_id = ?");
        $stmt->execute([$userId]);
    }

    private static function getClientIp() {
        if (class_exists('IpAddressValidator')) {
            return IpAddressValidator::getClientIp();
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
?>
