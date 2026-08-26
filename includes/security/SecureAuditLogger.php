<?php
/**
 * Logger sécurisé pour les audits de base de données
 * Stocke les logs en dehors du webroot avec données sensibles masquées
 */
class SecureAuditLogger {
    private const LOG_DIR = '/var/log/warriors-training-app/';

    /**
     * Log les requêtes de manière sécurisée
     */
    public static function logQuery($query, $params = []) {
        if (!is_dir(self::LOG_DIR)) {
            if (!@mkdir(self::LOG_DIR, 0750, true) && !is_dir(self::LOG_DIR)) {
                return;
            }
        }

        if (!is_writable(self::LOG_DIR)) {
            return;
        }

        $logFile = self::LOG_DIR . 'db_audit_' . date('Y-m-d') . '.log';

        $logEntry = [
            'timestamp' => date('c'),
            'query_type' => self::getQueryType($query),
            'table' => self::getTable($query),
            'ip_partial' => self::maskIp($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            'file' => basename(debug_backtrace()[1]['file'] ?? ''),
            'line' => debug_backtrace()[1]['line'] ?? ''
        ];

        @file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
        @chmod($logFile, 0640);
    }

    /**
     * Masque une adresse IP
     */
    private static function maskIp($ip) {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.*.* ';
        }
        return '[IPv6]';
    }

    /**
     * Extrait le type de requête
     */
    private static function getQueryType($query) {
        if (preg_match('/^SELECT/i', $query)) return 'SELECT';
        if (preg_match('/^INSERT/i', $query)) return 'INSERT';
        if (preg_match('/^UPDATE/i', $query)) return 'UPDATE';
        if (preg_match('/^DELETE/i', $query)) return 'DELETE';
        return 'OTHER';
    }

    /**
     * Extrait le nom de la table
     */
    private static function getTable($query) {
        if (preg_match('/FROM\s+`?(\w+)`?/i', $query, $matches)) {
            return $matches[1];
        }
        if (preg_match('/UPDATE\s+`?(\w+)`?/i', $query, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }
}
?>
