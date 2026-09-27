<?php

if (defined('WTC_DB_LOADED')) {
    return;
}
define('WTC_DB_LOADED', true);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../security/SecureAuditLogger.php';

try {
    $dotenvPath = __DIR__ . '/../../';
    if (is_file($dotenvPath . '.env')) {
        $dotenv = Dotenv\Dotenv::createUnsafeImmutable($dotenvPath);
        $dotenv->load();
    }
} catch (Throwable $e) {
    // Missing or unreadable .env is non-fatal; proceed with getenv defaults.
}

$env = static function (string $name, ?string $default = null): ?string {
    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return $value;
    }

    $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
    return ($value === null || $value === '') ? $default : (string) $value;
};

$host = $env('DB_HOST', '127.0.0.1');
$port = $env('DB_PORT', '3306');
$dbname = $env('DB_DATABASE');
$username = $env('DB_USERNAME');
$password = $env('DB_PASSWORD');

// Allow disabling DB audit logging for performance-sensitive environments
$auditDisabled = ($env('DISABLE_DB_AUDIT') === '1');

if (!function_exists('appendDbAuditLog')) {
    function appendDbAuditLog(string $event, string $sql, array $params = [], ?string $context = null, ?string $status = 'ok'): void
    {
        global $auditDisabled, $sqlActionAuditReady, $host, $port, $dbname, $username, $password;
        if (!empty($auditDisabled) || defined('WTC_DISABLE_SQL_ACTION_AUDIT')) {
            return;
        }

        try {
            SecureAuditLogger::logQuery($sql, []);
            if (empty($sqlActionAuditReady) || !in_array($event, ['sql_execute', 'sql_query', 'sql_exec'], true)) {
                return;
            }

            static $auditPdo = null;
            if (!$auditPdo instanceof PDO) {
                $auditPdo = new PDO(
                    "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
                    $username,
                    $password,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            }

            $sourcePage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'cli'));
            $sourceLine = 0;
            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8) as $frame) {
                $frameFile = (string) ($frame['file'] ?? '');
                if ($frameFile !== '' && realpath($frameFile) !== realpath(__FILE__) && basename($frameFile) !== 'db.php') {
                    $sourcePage = basename($frameFile);
                    $sourceLine = (int) ($frame['line'] ?? 0);
                    break;
                }
            }

            $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ipAddress = preg_replace('/\.\d+$/', '.0', $ipAddress) ?? '';
            } elseif (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $ipAddress = '[IPv6]';
            } else {
                $ipAddress = '';
            }

            $insertAudit = $auditPdo->prepare(
                'INSERT INTO sql_action_logs (actor_id, query_type, table_name, statement, status, source_page, source_line, ip_partial)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $actorId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
            $insertAudit->execute([
                $actorId !== false && $actorId > 0 ? $actorId : null,
                SecureAuditLogger::getQueryType(ltrim($sql)),
                mb_substr(SecureAuditLogger::getTable($sql), 0, 64),
                SecureAuditLogger::sanitizeQuery($sql),
                $status === 'ok' ? 'ok' : 'error',
                mb_substr($sourcePage, 0, 255),
                $sourceLine > 0 ? $sourceLine : null,
                $ipAddress !== '' ? $ipAddress : null,
            ]);
        } catch (Throwable $e) {
            error_log('[sql-action-audit] ' . $e->getMessage());
        }
    }
}

if (!class_exists('AuditPDOStatement', false)) {
    class AuditPDOStatement extends PDOStatement
    {
        protected $pdo;

        protected function __construct($pdo)
        {
            $this->pdo = $pdo;
        }

        public function execute($input_parameters = null): bool
        {
            $sql = $this->queryString;
            $params = is_array($input_parameters) ? $input_parameters : [];
            try {
                $result = parent::execute($input_parameters);
                appendDbAuditLog('sql_execute', $sql, $params, 'auto', 'ok');
                return $result;
            } catch (Throwable $error) {
                appendDbAuditLog('sql_execute', $sql, $params, 'auto', 'error');
                throw $error;
            }
        }
    }
}

if (!class_exists('AuditPDO', false)) {
    class AuditPDO extends PDO
    {
        public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
        {
            parent::__construct($dsn, $username, $password, $options);
            $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [AuditPDOStatement::class, [$this]]);
        }

        public function prepare(string $query, array $driver_options = []): PDOStatement
        {
            return parent::prepare($query, $driver_options);
        }

        public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
        {
            try {
                $statement = parent::query($query, $fetchMode, ...$fetchModeArgs);
                appendDbAuditLog('sql_query', $query, [], 'auto', 'ok');
                return $statement;
            } catch (Throwable $error) {
                appendDbAuditLog('sql_query', $query, [], 'auto', 'error');
                throw $error;
            }
        }

        public function exec(string $statement): int|false
        {
            try {
                $result = parent::exec($statement);
                appendDbAuditLog('sql_exec', $statement, [], 'auto', $result === false ? 'error' : 'ok');
                return $result;
            } catch (Throwable $error) {
                appendDbAuditLog('sql_exec', $statement, [], 'auto', 'error');
                throw $error;
            }
        }
    }
}

$ilycScoresAvailable = false;

try {
    $pdo = new AuditPDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $username, $password);

    $sqlActionAuditReady = false;
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS sql_action_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actor_id INT DEFAULT NULL,
                query_type VARCHAR(16) NOT NULL,
                table_name VARCHAR(64) NOT NULL DEFAULT \'unknown\',
                statement TEXT NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT \'ok\',
                source_page VARCHAR(255) NOT NULL DEFAULT \'unknown\',
                source_line INT UNSIGNED DEFAULT NULL,
                ip_partial VARCHAR(45) DEFAULT NULL,
                INDEX idx_sql_action_logs_created (id),
                INDEX idx_sql_action_logs_type_id (query_type, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $sqlActionAuditReady = true;
    } catch (Throwable $e) {
        error_log('[db.php] SQL action audit table unavailable: ' . $e->getMessage());
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS ilyc_scores (
                account_id INT NOT NULL PRIMARY KEY,
                score INT UNSIGNED NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_ilyc_scores_account FOREIGN KEY (account_id) REFERENCES account_wtc(id) ON DELETE CASCADE,
                INDEX idx_ilyc_scores_ranking (score, updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $ilycScoresAvailable = true;
    } catch (Throwable $e) {
        try {
            $pdo->query('SELECT 1 FROM ilyc_scores LIMIT 1');
            $ilycScoresAvailable = true;
        } catch (Throwable $tableError) {
            appendDbAuditLog('schema_migration_error', $tableError->getMessage(), [], 'db.php:ilyc_scores', 'error');
            error_log('[db.php] Impossible de préparer la table ilyc_scores: ' . $tableError->getMessage());
        }
    }

    try {
        $columnsStmt = $pdo->query("SHOW COLUMNS FROM account_wtc LIKE 'last_seen'");
        if ($columnsStmt->rowCount() === 0) {
            $pdo->exec('ALTER TABLE account_wtc ADD COLUMN last_seen DATETIME DEFAULT NULL');
            appendDbAuditLog('schema_migration', 'ALTER TABLE account_wtc ADD COLUMN last_seen DATETIME DEFAULT NULL', [], 'db.php', 'completed');
        }
    } catch (Throwable $e) {
        appendDbAuditLog('schema_migration_error', $e->getMessage(), [], 'db.php', 'error');
    }

    $indexMigrationFile = __DIR__ . '/../../data/db_index_version.txt';
    $indexMigrationVersion = is_file($indexMigrationFile) ? (int) @file_get_contents($indexMigrationFile) : 0;
    if ($indexMigrationVersion < 1) {
        $indexMigrationLock = @fopen($indexMigrationFile . '.lock', 'c');
        if ($indexMigrationLock !== false && flock($indexMigrationLock, LOCK_EX)) {
            try {
                $indexMigrationVersion = is_file($indexMigrationFile) ? (int) @file_get_contents($indexMigrationFile) : 0;
                if ($indexMigrationVersion < 1) {
                    $indexes = [
                        ['seances', 'idx_seances_date_start', ['date_seance', 'heure_debut']],
                        ['inscriptions_seances', 'idx_inscriptions_seance_creator', ['seance_id', 'inscrit_par']],
                        ['inscriptions_seances', 'idx_inscriptions_seance_created', ['seance_id', 'created_at']],
                    ];
                    $indexExists = $pdo->prepare(
                        'SELECT 1 FROM information_schema.statistics
                         WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
                         LIMIT 1'
                    );
                    foreach ($indexes as [$table, $indexName, $columns]) {
                        $indexExists->execute([$table, $indexName]);
                        if ($indexExists->fetchColumn() !== false) {
                            continue;
                        }
                        $quotedColumns = implode(', ', array_map(static fn($column) => '`' . $column . '`', $columns));
                        $pdo->exec('ALTER TABLE `' . $table . '` ADD INDEX `' . $indexName . '` (' . $quotedColumns . ')');
                    }
                    if (@file_put_contents($indexMigrationFile, '1', LOCK_EX) === false) {
                        throw new RuntimeException('Impossible d’enregistrer la version des index SQL.');
                    }
                }
            } catch (Throwable $e) {
                appendDbAuditLog('schema_migration_error', $e->getMessage(), [], 'db.php:indexes', 'error');
                error_log('[db.php] Impossible de préparer les index SQL: ' . $e->getMessage());
            } finally {
                flock($indexMigrationLock, LOCK_UN);
                fclose($indexMigrationLock);
            }
        }
    }

    if (!defined('WTC_SKIP_SEANCE_CLEANUP')) {
        // Nettoyage automatique des séances trop anciennes (plus de 3 mois).
        // To avoid running this expensive query on every request, throttle it to once per hour.
        $cleanupFile = __DIR__ . '/../../data/last_cleanup.txt';
        $runCleanup = true;
        try {
            if (is_file($cleanupFile)) {
                $last = (int) @file_get_contents($cleanupFile);
                if ($last > 0 && (time() - $last) < 3600) {
                    $runCleanup = false;
                }
            }
        } catch (Throwable $e) {
            // ignore and run cleanup
        }

        if ($runCleanup) {
            try {
                $pdo->exec('DELETE FROM seances WHERE date_seance < DATE_SUB(CURDATE(), INTERVAL 3 MONTH)');
                appendDbAuditLog('background_cleanup', 'DELETE FROM seances WHERE date_seance < DATE_SUB(CURDATE(), INTERVAL 3 MONTH)', [], 'db.php', 'completed');
                @file_put_contents($cleanupFile, (string) time());
            } catch (Throwable $e) {
                appendDbAuditLog('background_cleanup_error', $e->getMessage(), [], 'db.php', 'error');
            }
        }
    }
} catch (PDOException $e) {
    appendDbAuditLog('db_connection_error', $e->getMessage(), [], 'db.php', 'error');
    error_log('db.php connection error: ' . $e->getMessage());
    $pdo = null;
}
