<?php
/**
 * Gestionnaire de rate limiting
 * Limite le nombre de requêtes par utilisateur/IP
 */
class RateLimiter {
    private const STORAGE_FILE = '/var/lib/warriors-training-app/rate_limits.json';

    /**
     * Vérifie et enregistre une requête
     */
    public static function checkRateLimit($identifier, $maxRequests = 100, $timeWindow = 60) {
        $now = time();
        $limits = self::loadLimits();
        $key = hash('sha256', $identifier);

        if (!isset($limits[$key])) {
            $limits[$key] = [];
        }

        $limits[$key] = array_filter($limits[$key], function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });

        if (count($limits[$key]) >= $maxRequests) {
            throw new Exception(
                "Limite de requêtes dépassée. Réessayez dans " . ($timeWindow) . " secondes."
            );
        }

        $limits[$key][] = $now;
        self::saveLimits($limits);

        return true;
    }

    private static function loadLimits() {
        if (!file_exists(self::STORAGE_FILE)) {
            return [];
        }

        $data = json_decode(file_get_contents(self::STORAGE_FILE), true);
        return is_array($data) ? $data : [];
    }

    private static function saveLimits($data) {
        @mkdir(dirname(self::STORAGE_FILE), 0750, true);
        file_put_contents(self::STORAGE_FILE, json_encode($data), LOCK_EX);
        @chmod(self::STORAGE_FILE, 0640);
    }
}
?>
