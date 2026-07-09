<?php

require_once __DIR__ . '/db.php';

class PersistentToken
{
    private $pdo;
    private $tokenCookieName = 'wtc_auth_token';
    private $tokenDuration = 31536000; // 1 an en secondes

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function create($accountId)
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $this->tokenDuration);
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $ipAddress = $this->getClientIp();

        $stmt = $this->pdo->prepare('
            INSERT INTO persistent_tokens (account_id, token, user_agent, ip_address, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$accountId, $token, $userAgent, $ipAddress, $expiresAt]);

        $this->setTokenCookie($token);

        return $token;
    }

    public function validate()
    {
        $token = $_COOKIE[$this->tokenCookieName] ?? null;

        if (!$token) {
            return null;
        }

        $stmt = $this->pdo->prepare('
            SELECT pt.account_id, a.*, pt.id as token_id
            FROM persistent_tokens pt
            JOIN account_wtc a ON a.id = pt.account_id
            WHERE pt.token = ? AND pt.expires_at > NOW()
        ');
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            $this->clearToken();
            return null;
        }

        $updateStmt = $this->pdo->prepare('
            UPDATE persistent_tokens SET last_used = NOW() WHERE id = ?
        ');
        $updateStmt->execute([$result['token_id']]);

        if ((int)$result['ban'] === 1) {
            $this->clearToken();
            return null;
        }

        if ((int)$result['email_verified'] !== 1) {
            $this->clearToken();
            return null;
        }

        return $result;
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
                'samesite' => 'Lax'
            ]
        );
    }
    
    public function clear()
    {
        $token = $_COOKIE[$this->tokenCookieName] ?? null;

        if ($token) {
            $stmt = $this->pdo->prepare('DELETE FROM persistent_tokens WHERE token = ?');
            $stmt->execute([$token]);
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
                'samesite' => 'Lax'
            ]
        );
        unset($_COOKIE[$this->tokenCookieName]);
    }

    private function getClientIp()
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    public function cleanupExpired()
    {
        $stmt = $this->pdo->prepare('DELETE FROM persistent_tokens WHERE expires_at < NOW()');
        $stmt->execute();
    }
}
