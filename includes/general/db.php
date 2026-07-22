<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/../../');
$dotenv->load();

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_DATABASE');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');

$dbAuditPath = __DIR__ . '/../../data/db_audit.json';
$dbAuditDir = dirname($dbAuditPath);
if (!is_dir($dbAuditDir)) {
    @mkdir($dbAuditDir, 0775, true);
}

function maskAuditValue(string $key, mixed $value): mixed
{
    $normalizedKey = strtolower($key);
    if (str_contains($normalizedKey, 'password') || str_contains($normalizedKey, 'token') || str_contains($normalizedKey, 'secret')) {
        return '[masqué]';
    }

    if (is_scalar($value)) {
        $text = trim((string) $value);
        return $text === '' ? '[vide]' : $text;
    }

    if (is_array($value)) {
        return '[tableau]';
    }

    return '[valeur complexe]';
}

function sanitizeAuditParams(array $params): array
{
    $sanitized = [];
    foreach ($params as $key => $value) {
        if (is_string($key)) {
            $sanitized[$key] = maskAuditValue($key, $value);
        } else {
            $sanitized[] = maskAuditValue((string) $key, $value);
        }
    }

    return $sanitized;
}

function appendDbAuditLog(string $event, string $sql, array $params = [], ?string $context = null, ?string $status = 'ok'): void
{
    global $dbAuditPath;

    $entry = [
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'event' => $event,
        'sql' => trim($sql),
        'params' => sanitizeAuditParams($params),
        'context' => $context ?? 'auto',
        'status' => $status,
        'request' => [
            'script' => $_SERVER['SCRIPT_NAME'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
        ],
    ];

    $logs = [];
    if (is_file($dbAuditPath)) {
        $raw = @file_get_contents($dbAuditPath);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $logs = $decoded;
            }
        }
    }

    $logs[] = $entry;
    if (count($logs) > 5000) {
        $logs = array_slice($logs, -5000);
    }

    @file_put_contents($dbAuditPath, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

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

try {
    $pdo = new AuditPDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8", $username, $password);

    // Nettoyage automatique des séances trop anciennes (plus de 3 mois).
    $pdo->exec('DELETE FROM seances WHERE date_seance < DATE_SUB(CURDATE(), INTERVAL 3 MONTH)');
    appendDbAuditLog('background_cleanup', 'DELETE FROM seances WHERE date_seance < DATE_SUB(CURDATE(), INTERVAL 3 MONTH)', [], 'db.php', 'completed');
} catch (PDOException $e) {
    appendDbAuditLog('db_connection_error', $e->getMessage(), [], 'db.php', 'error');
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
