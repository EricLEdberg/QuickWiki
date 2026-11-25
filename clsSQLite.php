<?php
/**
 * clsSQLite.php
 * Small PDO/SQLite wrapper for QuickWiki.
 *
 * PHP 8.1+ with PDO_SQLITE enabled. Works on Windows/IIS and Linux.
 * This class never creates a database unless CREATE_IF_MISSING is explicitly true.
 */
declare(strict_types=1);

class clsSQLite
{
    public string $Version = '0.1-test';
    public bool $MyDebug = false;
    public bool $DISABLED = false;
    public ?string $error = null;
    public array $aResults = [];
    public int $QueryResultsCnt = 0;
    public int $LastInsertId = 0;
    public int $RowsAffected = 0;

    private ?PDO $pdo = null;
    private bool $ownsConnection = false;
    private array $config = [];

    public function __construct(array $aConfig = [], ?PDO $pdo = null)
    {
        $this->config = $aConfig;
        if ($pdo !== null) {
            $this->pdo = $pdo;
            $this->ownsConnection = false;
        }
    }

    public function __destruct()
    {
        $this->CloseDB();
    }

    public function InitDB(): bool
    {
        if ($this->pdo instanceof PDO) return true;

        try {
            if (!extension_loaded('pdo_sqlite')) {
                throw new RuntimeException('PHP PDO_SQLITE extension is not loaded.');
            }

            $path = $this->config['PATH'] ?? '';
            if (!is_string($path) || trim($path) === '') {
                throw new InvalidArgumentException('Config PATH must be a non-empty SQLite file path.');
            }

            $create = ($this->config['CREATE_IF_MISSING'] ?? false) === true;
            $path = $this->normalizePath($path);
            $parent = dirname($path);

            if (!is_dir($parent)) {
                throw new RuntimeException("Database directory does not exist: {$parent}");
            }
            if (file_exists($path) && !is_file($path)) {
                throw new RuntimeException('Database path exists but is not a regular file.');
            }
            if (!file_exists($path) && !$create) {
                throw new RuntimeException("Database file does not exist (creation disabled): {$path}");
            }

            // Verify directory/file permissions before opening. SQLite may also need write
            // access to the containing directory for journal/WAL files.
            if (!is_readable($parent) || !is_writable($parent)) {
                throw new RuntimeException("Database directory must be readable and writable: {$parent}");
            }
            if (file_exists($path) && (!is_readable($path) || !is_writable($path))) {
                throw new RuntimeException("Database file must be readable and writable: {$path}");
            }

            $dsn = 'sqlite:' . $path;
            $this->pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->ownsConnection = true;

            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $busyMs = max(0, (int)($this->config['BUSY_TIMEOUT_MS'] ?? 5000));
            $this->pdo->exec('PRAGMA busy_timeout = ' . $busyMs);

            if (($this->config['JOURNAL_MODE'] ?? '') !== '') {
                $mode = strtoupper((string)$this->config['JOURNAL_MODE']);
                if (!in_array($mode, ['DELETE', 'TRUNCATE', 'PERSIST', 'MEMORY', 'WAL', 'OFF'], true)) {
                    throw new InvalidArgumentException('Unsupported SQLite JOURNAL_MODE.');
                }
                $this->pdo->query('PRAGMA journal_mode = ' . $mode);
            }

            $this->DISABLED = false;
            $this->error = null;
            return true;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            $this->DISABLED = true;
            if ($this->ownsConnection) $this->pdo = null;
            $this->ownsConnection = false;
            return false;
        }
    }

    public function CloseDB(): void
    {
        // Drop only a connection created by this instance. Injected PDO belongs to caller.
        if ($this->ownsConnection) $this->pdo = null;
        $this->ownsConnection = false;
    }

    public function IsOpen(): bool { return $this->pdo instanceof PDO; }

    public function GetPDO(): ?PDO
    {
        if (!$this->ensureOpen()) return null;
        return $this->pdo;
    }

    public function ExecuteSQL(string $sql, array $params = []): bool
    {
        $stmt = $this->run($sql, $params);
        if ($stmt === false) return false;
        $this->RowsAffected = $stmt->rowCount();
        $this->LastInsertId = (int)$this->pdo->lastInsertId();
        return true;
    }

    /** Query rows as associative arrays. Empty result is successful and returns []. */
    public function QueryDB(string $sql, array $params = []): array|false
    {
        $stmt = $this->run($sql, $params);
        if ($stmt === false) return false;
        $this->aResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->QueryResultsCnt = count($this->aResults);
        $this->RowsAffected = $stmt->rowCount();
        return $this->aResults;
    }

    public function QueryScalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->run($sql, $params);
        if ($stmt === false) return false;
        return $stmt->fetchColumn();
    }

    public function BeginTransaction(): bool
    {
        if (!$this->ensureOpen()) return false;
        try { return $this->pdo->beginTransaction(); }
        catch (Throwable $e) { return $this->fail($e); }
    }

    public function Commit(): bool
    {
        if (!$this->ensureOpen()) return false;
        try { return $this->pdo->commit(); }
        catch (Throwable $e) { return $this->fail($e); }
    }

    public function Rollback(): bool
    {
        if (!$this->ensureOpen()) return false;
        try { return $this->pdo->inTransaction() ? $this->pdo->rollBack() : true; }
        catch (Throwable $e) { return $this->fail($e); }
    }

    /** Returns SQLite integrity_check rows (normally one row: "ok"). */
    public function IntegrityCheck(): array|false
    {
        return $this->QueryDB('PRAGMA integrity_check');
    }

    /** Returns foreign_key_check violations; [] means no violations. */
    public function ForeignKeyCheck(): array|false
    {
        return $this->QueryDB('PRAGMA foreign_key_check');
    }

    /** Snapshot user tables, views, indexes and triggers, excluding SQLite internals. */
    public function GetSchema(): array|false
    {
        return $this->QueryDB(
            "SELECT type, name, tbl_name, sql FROM sqlite_master " .
            "WHERE name NOT LIKE 'sqlite_%' ORDER BY type, name"
        );
    }

    public function DumpResults(?array $rows = null): string
    {
        $rows ??= $this->aResults;
        return print_r($rows, true);
    }

    public function RegisterFunction(string $name, callable $callback, int $argc = -1): bool
    {
        if (!$this->ensureOpen()) return false;
        try {
            if (!$this->pdo->sqliteCreateFunction($name, $callback, $argc)) {
                throw new RuntimeException("Unable to register SQLite function: {$name}");
            }
            return true;
        } catch (Throwable $e) { return $this->fail($e); }
    }

    private function run(string $sql, array $params): PDOStatement|false
    {
        if (!$this->ensureOpen()) return false;
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $this->error = null;
            return $stmt;
        } catch (Throwable $e) {
            $this->fail($e);
            return false;
        }
    }

    private function ensureOpen(): bool
    {
        if ($this->pdo instanceof PDO) return true;
        return $this->InitDB();
    }

    private function fail(Throwable $e): false
    {
        $this->error = $e->getMessage();
        if ($this->MyDebug) error_log('clsSQLite: ' . $this->error);
        return false;
    }

    private function normalizePath(string $path): string
    {
        // SQLite accepts both slash styles on Windows; PHP filesystem functions work
        // with native separators. Relative paths are resolved from the process CWD.
        $path = trim($path);
        if (DIRECTORY_SEPARATOR === '\\') $path = str_replace('/', '\\', $path);
        return $path;
    }
}
