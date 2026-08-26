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

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_DATABASE');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');

// Allow disabling DB audit logging for performance-sensitive environments
$auditDisabled = (getenv('DISABLE_DB_AUDIT') === '1');

if (!function_exists('appendDbAuditLog')) {
    function appendDbAuditLog(string $event, string $sql, array $params = [], ?string $context = null, ?string $status = 'ok'): void
    {
        global $auditDisabled;
        if (!empty($auditDisabled)) {
            return;
        }

        try {
            if (str_contains(strtoupper($sql), 'SELECT')) {
                SecureAuditLogger::logQuery('SELECT', 'query', $params);
            } elseif (str_contains(strtoupper($sql), 'INSERT')) {
                SecureAuditLogger::logQuery('INSERT', 'query', $params);
            } elseif (str_contains(strtoupper($sql), 'UPDATE')) {
                SecureAuditLogger::logQuery('UPDATE', 'query', $params);
            } elseif (str_contains(strtoupper($sql), 'DELETE')) {
                SecureAuditLogger::logQuery('DELETE', 'query', $params);
            }
        } catch (Exception $e) {
            error_log("Audit logging error: " . $e->getMessage());
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
            $sql = trim((string) $this->queryString);
            if ($sql !== '') {
                appendDbAuditLog('statement_created', $sql, [], 'auto', 'created');
            }
        }

        public function execute($input_parameters = null): bool
        {
            $sql = $this->queryString;
            $params = is_array($input_parameters) ? $input_parameters : [];
            appendDbAuditLog('statement_execute', $sql, $params, 'auto', 'executed');
            return parent::execute($input_parameters);
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
            appendDbAuditLog('statement_prepare', $query, [], 'auto', 'prepared');
            return parent::prepare($query, $driver_options);
        }

        public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
        {
            appendDbAuditLog('statement_query', $query, [], 'auto', 'query');
            return parent::query($query, $fetchMode, ...$fetchModeArgs);
        }

        public function exec(string $statement): int|false
        {
            appendDbAuditLog('statement_exec', $statement, [], 'auto', 'exec');
            return parent::exec($statement);
        }
    }
}

try {
    $pdo = new AuditPDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8", $username, $password);

    try {
        $columnsStmt = $pdo->query("SHOW COLUMNS FROM account_wtc LIKE 'last_seen'");
        if ($columnsStmt->rowCount() === 0) {
            $pdo->exec('ALTER TABLE account_wtc ADD COLUMN last_seen DATETIME DEFAULT NULL');
            appendDbAuditLog('schema_migration', 'ALTER TABLE account_wtc ADD COLUMN last_seen DATETIME DEFAULT NULL', [], 'db.php', 'completed');
        }
    } catch (Throwable $e) {
        appendDbAuditLog('schema_migration_error', $e->getMessage(), [], 'db.php', 'error');
    }

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
} catch (PDOException $e) {
    appendDbAuditLog('db_connection_error', $e->getMessage(), [], 'db.php', 'error');
    error_log('db.php connection error: ' . $e->getMessage());
    $pdo = null;
}
