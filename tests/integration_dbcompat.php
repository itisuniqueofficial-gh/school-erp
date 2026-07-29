<?php

declare(strict_types=1);

/**
 * Database index-length compatibility test.
 *
 * Guarantees a fresh install succeeds on the STRICTEST InnoDB limit (767-byte
 * index prefix — MySQL 5.6 / MariaDB with innodb_large_prefix off), not just on
 * modern servers. It measures every index's real prefix byte-length from
 * information_schema (honouring utf8mb4 and prefix indexes) and asserts none
 * exceeds 767 bytes. If this passes, error 1071 cannot occur on any supported
 * MySQL/MariaDB version.
 */

require __DIR__ . '/../bootstrap/app.php';

use Core\Database\Connection;

$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

$conn = Connection::instance();

// Server version (compatibility awareness).
$ver = (string) ($conn->selectOne('SELECT VERSION() AS v')['v'] ?? '');
echo "  server: {$ver}\n";
$check('server version detected', $ver !== '');

// Per-index prefix byte length. For string columns CHARACTER_OCTET_LENGTH is the
// full byte length (utf8mb4-aware); a prefix index uses SUB_PART * bytes/char;
// non-string columns fall back to a small fixed size (<= 8 bytes).
$sql = <<<SQL
SELECT s.TABLE_NAME AS t, s.INDEX_NAME AS idx,
  ROUND(SUM(
    CASE
      WHEN s.SUB_PART IS NOT NULL AND c.CHARACTER_MAXIMUM_LENGTH > 0
        THEN s.SUB_PART * (c.CHARACTER_OCTET_LENGTH / c.CHARACTER_MAXIMUM_LENGTH)
      WHEN c.CHARACTER_OCTET_LENGTH IS NOT NULL
        THEN c.CHARACTER_OCTET_LENGTH
      ELSE 8
    END
  )) AS prefix_bytes
FROM information_schema.STATISTICS s
JOIN information_schema.COLUMNS c
  ON c.TABLE_SCHEMA = s.TABLE_SCHEMA AND c.TABLE_NAME = s.TABLE_NAME AND c.COLUMN_NAME = s.COLUMN_NAME
WHERE s.TABLE_SCHEMA = DATABASE()
GROUP BY s.TABLE_NAME, s.INDEX_NAME
ORDER BY prefix_bytes DESC
SQL;

$rows = $conn->select($sql);
$check('indexes discovered', count($rows) > 0);

$offenders = array_filter($rows, static fn ($r) => (int) $r['prefix_bytes'] > 767);
if ($offenders !== []) {
    foreach ($offenders as $o) {
        echo "  OVER 767: {$o['t']}.{$o['idx']} = {$o['prefix_bytes']} bytes\n";
    }
}
$check('no index exceeds the 767-byte limit (MySQL 5.6-safe)', $offenders === []);

// Portable data types only: the JSON type is unsupported on MySQL 5.6 and
// cannot take a DEFAULT before 8.0.13, so we store JSON as LONGTEXT and validate
// in the app. Guard against any column reintroducing the JSON type.
$jsonCols = $conn->select("SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = 'json'");
if ($jsonCols !== []) {
    foreach ($jsonCols as $j) { echo "  JSON column: {$j['t']}.{$j['c']}\n"; }
}
$check('no column uses the non-portable JSON type (use LONGTEXT)', $jsonCols === []);

// The previously-oversized composite is now within budget.
$extmap = null;
foreach ($rows as $r) {
    if ($r['idx'] === 'uq_extmap_provider_type_external') { $extmap = (int) $r['prefix_bytes']; }
}
$check('external_mappings composite index is <= 767 bytes', $extmap !== null && $extmap <= 767);

// Largest index for visibility.
if ($rows !== []) {
    echo "  largest index: {$rows[0]['t']}.{$rows[0]['idx']} = {$rows[0]['prefix_bytes']} bytes\n";
}

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
