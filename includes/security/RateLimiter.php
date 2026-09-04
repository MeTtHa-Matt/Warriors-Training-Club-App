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
        if (!is_file(self::STORAGE_FILE) || !is_readable(self::STORAGE_FILE)) {
            return [];
        }

        $content = @file_get_contents(self::STORAGE_FILE);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private static function saveLimits($data) {
        $directory = dirname(self::STORAGE_FILE);
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            error_log('[RateLimiter] impossible de créer le répertoire de stockage: ' . $directory);
            return;
        }

        if (!is_writable($directory)) {
            error_log('[RateLimiter] répertoire de stockage non accessible en écriture: ' . $directory);
            return;
        }

        if (@file_put_contents(self::STORAGE_FILE, json_encode($data), LOCK_EX) === false) {
            error_log('[RateLimiter] impossible d’écrire le fichier de stockage: ' . self::STORAGE_FILE);
            return;
        }

        @chmod(self::STORAGE_FILE, 0640);
    }
}
?>
