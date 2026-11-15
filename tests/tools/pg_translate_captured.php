<?php
/** Dev tool: translate captured MySQL-flavoured statements for PostgreSQL (JSON in/out) so they can be PREPAREd on a real server. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/core/Database/Dialect.php';
$d = new Core\Database\Dialect('pgsql');
$seen = [];
foreach (file($argv[1], FILE_IGNORE_NEW_LINES) as $line) {
    $sql = json_decode($line, true);
    if (is_string($sql)) { $seen[$sql] = true; }
}
$out = [];
foreach (array_keys($seen) as $sql) { $out[] = ['orig' => $sql, 'pg' => $d->translate($sql)]; }
echo json_encode($out);
