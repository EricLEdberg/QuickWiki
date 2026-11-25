<?php
/**
 * Browser-based smoke/regression test for clsSQLite.
 * Open this file through a PHP-enabled web server (IIS/FastCGI or Linux).
 * The test database is created temporarily in ./Uploads and removed afterward.
 */
declare(strict_types=1);
require_once __DIR__ . '/clsSQLite.php';

header('Content-Type: text/html; charset=UTF-8');

function testAssert(bool $ok, string $message): void
{
    $status = $ok ? 'PASS' : 'FAIL';
    $class = $ok ? 'pass' : 'fail';
    echo '<li class="' . $class . '"><strong>[' . $status . ']</strong> '
        . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
    if (!$ok) throw new RuntimeException($message);
}

echo '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>clsSQLite browser test</title>
<style>
body{font:16px/1.5 system-ui,sans-serif;max-width:900px;margin:2rem auto;padding:0 1rem;color:#222}
.pass{color:#176b2c}.fail{color:#a31515}pre{padding:1rem;background:#f4f4f4;overflow:auto}
</style>
</head>
<body>
<h1>clsSQLite browser test</h1>
<ol>';

$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'Uploads';
$dbFile = $uploadDir . DIRECTORY_SEPARATOR . 'quickwiki_sqlite_test_' . bin2hex(random_bytes(4)) . '.sqlite';
$db = null;
$exitCode = 0;

try {
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create ./Uploads directory.');
    }
    if (!is_writable($uploadDir)) {
        throw new RuntimeException('./Uploads must be writable by the web server account.');
    }

    $db = new clsSQLite([
        'PATH' => $dbFile,
        'CREATE_IF_MISSING' => true,
        'BUSY_TIMEOUT_MS' => 3000,
        'JOURNAL_MODE' => 'WAL',
    ]);

    testAssert($db->InitDB(), 'Open/create temporary SQLite database in ./Uploads');
    testAssert($db->ExecuteSQL('CREATE TABLE authors (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE)'), 'Create authors table');
    testAssert($db->ExecuteSQL('CREATE TABLE articles (id INTEGER PRIMARY KEY, author_id INTEGER NOT NULL REFERENCES authors(id), title TEXT NOT NULL, body TEXT, published INTEGER NOT NULL DEFAULT 0)'), 'Create articles table');
    testAssert($db->ExecuteSQL('CREATE INDEX ix_articles_author ON articles(author_id)'), 'Create index');
    testAssert($db->ExecuteSQL('CREATE VIEW published_articles AS SELECT a.id, a.title, u.name AS author FROM articles a JOIN authors u ON u.id=a.author_id WHERE a.published=1'), 'Create view');

    testAssert($db->ExecuteSQL('INSERT INTO authors(name) VALUES (:name)', [':name' => 'Ada Lovelace']), 'Insert author using parameter');
    testAssert($db->ExecuteSQL('INSERT INTO articles(author_id,title,body,published) VALUES (:aid,:title,:body,1)', [
        ':aid' => 1, ':title' => 'Notes on Engines', ':body' => 'A test body',
    ]), 'Insert article using parameters');

    $rows = $db->QueryDB('SELECT title, author FROM published_articles');
    testAssert(is_array($rows) && count($rows) === 1 && $rows[0]['author'] === 'Ada Lovelace', 'Query view and fetch associative rows');

    testAssert($db->RegisterFunction('qw_test_upper', static fn($s) => strtoupper((string)$s), 1), 'Register SQLite user function');
    testAssert($db->QueryScalar('SELECT qw_test_upper(?)', ['quickwiki']) === 'QUICKWIKI', 'Call registered SQLite function');

    testAssert($db->BeginTransaction(), 'Begin transaction');
    testAssert($db->ExecuteSQL('INSERT INTO authors(name) VALUES (?)', ['Grace Hopper']), 'Insert within transaction');
    testAssert($db->Rollback(), 'Rollback transaction');
    testAssert((int)$db->QueryScalar('SELECT COUNT(*) FROM authors') === 1, 'Rollback left no extra author');

    $schema = $db->GetSchema();
    $schemaNames = is_array($schema) ? array_column($schema, 'name') : [];
    testAssert(in_array('authors', $schemaNames, true) && in_array('published_articles', $schemaNames, true) && in_array('ix_articles_author', $schemaNames, true), 'Schema listing includes table, view and index');

    $integrity = $db->IntegrityCheck();
    testAssert(is_array($integrity) && ($integrity[0]['integrity_check'] ?? '') === 'ok', 'PRAGMA integrity_check');
    $fk = $db->ForeignKeyCheck();
    testAssert(is_array($fk) && count($fk) === 0, 'PRAGMA foreign_key_check has no violations');

    echo '</ol><h2>Schema dump</h2>' . $db->DumpResults($schema);
    echo '<h2>Query dump</h2>' . $db->DumpResults($rows);
    echo '<p class="pass"><strong>Database test completed.</strong></p>';
} catch (Throwable $e) {
    error_log('clsSQLiteTest ERROR: ' . $e->getMessage());
    echo '</ol><p class="fail"><strong>TEST ERROR:</strong> '
        . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    $exitCode = 1;
} finally {
    if ($db instanceof clsSQLite) $db->CloseDB();
    foreach ([$dbFile, $dbFile . '-wal', $dbFile . '-shm', $dbFile . '-journal'] as $file) {
        if (is_file($file)) @unlink($file);
    }
}

echo '</body></html>';
exit($exitCode);
