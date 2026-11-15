<?php
/** Dev tool: print the PostgreSQL statements the Migrator would run, as JSON (for validating on a real PostgreSQL server). */
declare(strict_types=1);
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$m = new Database\Migrator();
$out = [];
$files = array_merge(glob(dirname(__DIR__, 2) . '/database/migrations/*.sql'), glob(dirname(__DIR__, 2) . '/database/migrations/*.php'));
sort($files);
foreach ($files as $f) {
    $out[basename($f)] = $m->postgresStatementsForFile($f);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
