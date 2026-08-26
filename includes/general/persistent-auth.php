<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../security/IpAddressValidator.php';
require_once __DIR__ . '/../security/SecurePersistentAuthToken.php';

class PersistentToken
{
    private $pdo;
    private $tokenCookieName = 'wtc_auth_token';
    private $tokenDuration = 2592000; // 30 jours en secondes

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function create($accountId)
    {
        if (empty($this->pdo)) {
            return null;
        }
        
        try {
            $token = SecurePersistentAuthToken::generateToken($accountId, $this->pdo);
            $this->setTokenCookie($token);
            return $token;
        } catch (Exception $e) {
            error_log("Erreur création token persistant: " . $e->getMessage());
            return null;
        }
    }

    public function validate()
    {
        if (empty($this->pdo)) {
            $this->clearToken();
            return null;
        }
        
        $token = $_COOKIE[$this->tokenCookieName] ?? null;

        if (!$token) {
            return null;
        }

        try {
            $tokenHash = hash('sha256', $token);
            $accountStmt = $this->pdo->prepare(
                'SELECT account_id FROM persistent_tokens WHERE token = ? AND expires_at > NOW() LIMIT 1'
            );
            $accountStmt->execute([$tokenHash]);
            $accountId = (int) $accountStmt->fetchColumn();
            if ($accountId <= 0) {
                $this->clearToken();
                return null;
            }

            // Valider et potentiellement rotater le token
            $newToken = SecurePersistentAuthToken::validateAndRotateToken($token, $accountId, $this->pdo);
            
            if (!$newToken) {
                $this->clearToken();
                return null;
            }
            
            // Si le token a été rotaté, mettre à jour le cookie
            if ($newToken !== $token) {
                $this->setTokenCookie($newToken);
            }
            
            // Récupérer les infos utilisateur
            $stmt = $this->pdo->prepare('
                SELECT *
                FROM account_wtc
                WHERE id = ?
            ');
            $stmt->execute([$accountId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                $this->clearToken();
                return null;
            }

            if ((int)$result['ban'] === 1) {
                $this->clearToken();
                return null;
            }

            if ((int)$result['email_verified'] !== 1) {
                $this->clearToken();
                return null;
            }

            return $result;
        } catch (Exception $e) {
            error_log("Erreur validation token: " . $e->getMessage());
            $this->clearToken();
            return null;
        }
    }
    
    private function setTokenCookie($token)
    {
        setcookie(
            $this->tokenCookieName,
            $token,
            [
                'expires' => time() + $this->tokenDuration,
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }
    
    public function clear()
    {
        $token = $_COOKIE[$this->tokenCookieName] ?? null;

        if ($token && !empty($this->pdo)) {
            try {
                SecurePersistentAuthToken::invalidateToken($_SESSION['user_id'] ?? 0, $this->pdo);
            } catch (Exception $e) {
                error_log("Erreur suppression token: " . $e->getMessage());
            }
        }

        $this->clearToken();
    }

    private function clearToken()
    {
        setcookie(
            $this->tokenCookieName,
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
        unset($_COOKIE[$this->tokenCookieName]);
    }

    public function cleanupExpired()
    {
        if (empty($this->pdo)) {
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare('DELETE FROM persistent_tokens WHERE expires_at < NOW()');
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Erreur nettoyage tokens expirés: " . $e->getMessage());
        }
    }
}
