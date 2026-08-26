<?php
/**
 * Gestionnaire avancé des tentatives de connexion
 * Implémente une escalade exponentielle des blocages
 */
class LoginAttemptThrottler {
    private const STORAGE_PATH = '/var/log/warriors-training-app/login_attempts.json';
    private const THROTTLING_CONFIG = [
        1 => ['max_attempts' => 5, 'lockout_seconds' => 60],
        2 => ['max_attempts' => 3, 'lockout_seconds' => 300],
        3 => ['max_attempts' => 2, 'lockout_seconds' => 1800],
        4 => ['max_attempts' => 1, 'lockout_seconds' => 3600]
    ];

    /**
     * Vérifie si une tentative de connexion est autorisée
     */
    public static function isLoginAllowed($email, $clientIp) {
        $attempts = self::getAttempts($email, $clientIp);
        $attempts = self::cleanOldAttempts($attempts);

        $recentAttempts = count($attempts);

        if ($recentAttempts > 0) {
            $lastAttempt = end($attempts);
            $lockoutLevel = min(4, ceil($recentAttempts / 3));
            $lockoutDuration = self::THROTTLING_CONFIG[$lockoutLevel]['lockout_seconds'];

            if ($lastAttempt['timestamp'] + $lockoutDuration > time()) {
                $remainingSeconds = ($lastAttempt['timestamp'] + $lockoutDuration) - time();
                throw new Exception(
                    "Compte temporairement verrouillé. Réessayez dans " . ceil($remainingSeconds / 60) . " minutes."
                );
            }
        }

        return true;
    }

    /**
     * Enregistre une tentative échouée
     */
    public static function recordFailedAttempt($email, $clientIp) {
        $attempts = self::getAttempts($email, $clientIp);

        $attempts[] = [
            'timestamp' => time(),
            'ip' => $clientIp,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100)
        ];

        $attempts = array_slice($attempts, -20);
        self::saveAttempts($email, $attempts);

        if (count($attempts) > 15) {
            error_log("Tentatives de connexion suspectes pour: $email depuis $clientIp");
        }
    }

    /**
     * Enregistre une connexion réussie
     */
    public static function recordSuccessfulLogin($email) {
        $data = self::loadAllAttempts();
        $emailKey = hash('sha256', $email);
        unset($data[$emailKey]);
        self::saveAllAttempts($data);
    }

    private static function getAttempts($email, $clientIp) {
        $data = self::loadAllAttempts();
        $emailKey = hash('sha256', $email);

        if (!isset($data[$emailKey])) {
            return [];
        }

        return array_filter($data[$emailKey], function($attempt) use ($clientIp) {
            return $attempt['ip'] === $clientIp;
        });
    }

    private static function cleanOldAttempts($attempts) {
        $now = time();
        return array_filter($attempts, function($attempt) use ($now) {
            return ($now - $attempt['timestamp']) < 7200;
        });
    }

    private static function loadAllAttempts() {
        if (!file_exists(self::STORAGE_PATH)) {
            return [];
        }

        $data = json_decode(file_get_contents(self::STORAGE_PATH), true);
        return is_array($data) ? $data : [];
    }

    private static function saveAttempts($email, $attempts) {
        $data = self::loadAllAttempts();
        $emailKey = hash('sha256', $email);
        $data[$emailKey] = $attempts;
        self::saveAllAttempts($data);
    }

    private static function saveAllAttempts($data) {
        @mkdir(dirname(self::STORAGE_PATH), 0750, true);
        file_put_contents(self::STORAGE_PATH, json_encode($data), LOCK_EX);
        @chmod(self::STORAGE_PATH, 0640);
    }
}
?>
