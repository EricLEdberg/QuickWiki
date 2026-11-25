<?php
/**
 * Standalone smoke/regression test for clsSQLite.
 * Run: php clsSQLiteTest.php
 * Creates a temporary database in the system temp directory and removes it afterward.
 */
require_once __DIR__ . '/clsSQLite.php';

function testAssert(bool $ok, string $message): void
{
    echo ($ok ? "[PASS] " : "[FAIL] ") . $message . PHP_EOL;
    if (!$ok) throw new RuntimeException($message);
}

$dbFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'quickwiki_sqlite_test_' . bin2hex(random_bytes(4)) . '.sqlite';
$db = new clsSQLite([
    'PATH' => $dbFile,
    'CREATE_IF_MISSING' => true,
    'BUSY_TIMEOUT_MS' => 3000,
    'JOURNAL_MODE' => 'WAL',
]);
$exitCode = 0;
try {
    testAssert($db->InitDB(), 'open/create temporary SQLite database');
    testAssert($db->ExecuteSQL('CREATE TABLE authors (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE)'), 'create authors table');
    testAssert($db->ExecuteSQL('CREATE TABLE articles (id INTEGER PRIMARY KEY, author_id INTEGER NOT NULL REFERENCES authors(id), title TEXT NOT NULL, body TEXT, published INTEGER NOT NULL DEFAULT 0)'), 'create articles table');
    testAssert($db->ExecuteSQL('CREATE INDEX ix_articles_author ON articles(author_id)'), 'create index');
    testAssert($db->ExecuteSQL('CREATE VIEW published_articles AS SELECT a.id, a.title, u.name AS author FROM articles a JOIN authors u ON u.id=a.author_id WHERE a.published=1'), 'create view');

    testAssert($db->ExecuteSQL('INSERT INTO authors(name) VALUES (:name)', [':name' => 'Ada Lovelace']), 'insert author using parameter');
    testAssert($db->ExecuteSQL('INSERT INTO articles(author_id,title,body,published) VALUES (:aid,:title,:body,1)', [
        ':aid' => 1, ':title' => 'Notes on Engines', ':body' => 'A test body',
    ]), 'insert article using parameters');

    $rows = $db->QueryDB('SELECT title, author FROM published_articles');
    testAssert(is_array($rows) && count($rows) === 1 && $rows[0]['author'] === 'Ada Lovelace', 'query view and fetch associative rows');

    testAssert($db->RegisterFunction('qw_test_upper', static fn($s) => strtoupper((string)$s), 1), 'register SQLite user function');
    testAssert($db->QueryScalar('SELECT qw_test_upper(?)', ['quickwiki']) === 'QUICKWIKI', 'call registered SQLite function');

    testAssert($db->BeginTransaction(), 'begin transaction');
    testAssert($db->ExecuteSQL('INSERT INTO authors(name) VALUES (?)', ['Grace Hopper']), 'insert within transaction');
    testAssert($db->Rollback(), 'rollback transaction');
    testAssert((int)$db->QueryScalar('SELECT COUNT(*) FROM authors') === 1, 'rollback left no extra author');

    $schema = $db->GetSchema();
    $schemaNames = is_array($schema) ? array_column($schema, 'name') : [];
    testAssert(in_array('authors', $schemaNames, true) && in_array('published_articles', $schemaNames, true) && in_array('ix_articles_author', $schemaNames, true), 'schema listing includes table, view and index');

    $integrity = $db->IntegrityCheck();
    testAssert(is_array($integrity) && ($integrity[0]['integrity_check'] ?? '') === 'ok', 'PRAGMA integrity_check');
    $fk = $db->ForeignKeyCheck();
    testAssert(is_array($fk) && count($fk) === 0, 'PRAGMA foreign_key_check has no violations');

    echo PHP_EOL . "Schema dump:" . PHP_EOL . $db->DumpResults($schema) . PHP_EOL;
    echo "Query dump:" . PHP_EOL . $db->DumpResults($rows) . PHP_EOL;
    echo "Database test completed." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "TEST ERROR: " . $e->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    $db->CloseDB();
    foreach ([$dbFile, $dbFile . '-wal', $dbFile . '-shm', $dbFile . '-journal'] as $file) {
        if (is_file($file)) @unlink($file);
    }
}
exit($exitCode);
