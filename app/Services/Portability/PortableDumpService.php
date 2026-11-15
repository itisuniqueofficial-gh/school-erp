<?php

declare(strict_types=1);

namespace App\Services\Portability;

use Core\Database\Connection;
use Core\Database\SchemaInspector;
use RuntimeException;

/**
 * Engine-neutral logical backup ("ERP dump"): export from MySQL, MariaDB or
 * PostgreSQL and import into any of them. It is the supported route for
 * cross-engine moves; native tools (mysqldump / pg_dump) stay the right
 * choice for same-engine disaster recovery of very large databases.
 *
 * File format (gzip-compressed NDJSON, optionally AES-256-GCM encrypted):
 *   {"type":"header","format":1,"app":"school-erp","engine":"mysql","created":"...","migrations":[...]}
 *   {"type":"table","name":"students","columns":["id","uid",...]}
 *   {"t":"students","r":[1,"2000...",...]}          one line per row
 *   {"type":"footer","tables":{"students":{"rows":120,"sha256":"..."}},"total_rows":N}
 *
 * The footer is written last, so a truncated or edited file fails verify():
 * verification recomputes every row count and SHA-256 and compares them with
 * the footer. A backup is never reported valid without that check.
 *
 * Not carried: triggers, views, routines, events, grants. They are recreated
 * by migrations (triggers) or are not used by the application.
 */
final class PortableDumpService
{
    public const FORMAT = 1;
    private const MAGIC = "ERPENC1";
    /** Tables filled by migrations themselves: replaced by the dump's content on import. */
    private const MIGRATION_SEEDED = ['id_sequences', 'permissions', 'role_permissions'];
    /** Tracked per database, never dumped. */
    private const SKIP = ['migrations', 'migration_log'];
    private const BATCH = 1000;

    private Connection $conn;
    private SchemaInspector $schema;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? Connection::instance();
        $this->schema = new SchemaInspector($this->conn);
    }

    // ------------------------------------------------------------------ export

    /**
     * @return array{path:string,bytes:int,tables:int,rows:int,sha256:string,encrypted:bool,engine:string}
     */
    public function export(string $path, ?string $passphrase = null): array
    {
        $dir = dirname($path);
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new RuntimeException('The backup directory does not exist or is not writable.');
        }
        if (file_exists($path)) {
            throw new RuntimeException('Refusing to overwrite an existing file: ' . basename($path));
        }

        $tmp = $path . '.part';
        $gz = gzopen($tmp, 'wb6');
        if ($gz === false) {
            throw new RuntimeException('Could not create the backup file.');
        }
        $applied = array_map('strval', array_column($this->conn->select('SELECT `migration` FROM `migrations` ORDER BY `id`'), 'migration'));
        $line = static fn (array $a): string => json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        gzwrite($gz, $line(['type' => 'header', 'format' => self::FORMAT, 'app' => 'school-erp', 'engine' => $this->conn->engine(),
            'server' => $this->schema->serverVersion(), 'created' => date('c'), 'migrations' => $applied]));

        $tables = array_values(array_diff($this->schema->tables(), self::SKIP));
        $stats = [];
        $total = 0;
        $d = $this->conn->dialect();
        foreach ($this->schema->insertOrder($tables) as $table) {
            $cols = $this->schema->writableColumns($table);
            gzwrite($gz, $line(['type' => 'table', 'name' => $table, 'columns' => $cols]));
            $colSql = implode(',', array_map([$d, 'quote'], $cols));
            $orderSql = implode(',', range(1, count($cols)));
            $hash = hash_init('sha256');
            $rows = 0;
            for ($offset = 0; ; $offset += self::BATCH) {
                $batch = $this->conn->select(
                    "SELECT {$colSql} FROM " . $d->quote($table) . " ORDER BY {$orderSql} LIMIT " . self::BATCH . ' OFFSET ' . $offset
                );
                if ($batch === []) {
                    break;
                }
                foreach ($batch as $row) {
                    $vals = array_map([$this, 'encodeValue'], array_values($row));
                    $json = json_encode($vals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                    hash_update($hash, $json . "\n");
                    gzwrite($gz, '{"t":' . json_encode($table) . ',"r":' . $json . "}\n");
                    $rows++;
                }
                if (count($batch) < self::BATCH) {
                    break;
                }
            }
            $stats[$table] = ['rows' => $rows, 'sha256' => hash_final($hash)];
            $total += $rows;
        }
        gzwrite($gz, $line(['type' => 'footer', 'tables' => $stats, 'total_rows' => $total]));
        gzclose($gz);

        $bytes = (string) file_get_contents($tmp);
        if ($passphrase !== null && $passphrase !== '') {
            $bytes = $this->encrypt($bytes, $passphrase);
        }
        if (file_put_contents($tmp, $bytes, LOCK_EX) === false || !@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Could not finalise the backup file.');
        }
        @chmod($path, 0600);

        // Never report success without re-reading the file we just wrote.
        $check = $this->verify($path, $passphrase);
        if (!$check['ok']) {
            @unlink($path);
            throw new RuntimeException('The backup failed its own verification and was removed: ' . $check['message']);
        }

        return ['path' => $path, 'bytes' => (int) filesize($path), 'tables' => count($stats), 'rows' => $total,
            'sha256' => hash_file('sha256', $path), 'encrypted' => $passphrase !== null && $passphrase !== '', 'engine' => $this->conn->engine()];
    }

    // ------------------------------------------------------------------ verify

    /**
     * Fully re-read a dump and compare it with its own footer.
     *
     * @return array{ok:bool,message:string,tables:int,rows:int,engine:?string,created:?string,migrations:string[],stats:array<string,array{rows:int,sha256:string}>,problems:string[]}
     */
    public function verify(string $path, ?string $passphrase = null): array
    {
        $r = ['ok' => false, 'message' => '', 'tables' => 0, 'rows' => 0, 'engine' => null, 'created' => null, 'migrations' => [], 'stats' => [], 'problems' => []];
        $fh = $this->open($path, $passphrase, $err);
        if ($fh === null) {
            $r['message'] = $err;
            return $r;
        }
        $header = null;
        $footer = null;
        $seen = [];
        $hashes = [];
        $declared = [];
        $n = 0;
        while (($raw = fgets($fh)) !== false) {
            $n++;
            $o = json_decode($raw, true);
            if (!is_array($o)) {
                $r['problems'][] = "line {$n} is not valid JSON";
                break;
            }
            if (($o['type'] ?? '') === 'header') {
                $header = $o;
            } elseif (($o['type'] ?? '') === 'table') {
                $declared[$o['name']] = count($o['columns']);
                $seen[$o['name']] = 0;
                $hashes[$o['name']] = hash_init('sha256');
            } elseif (($o['type'] ?? '') === 'footer') {
                $footer = $o;
            } elseif (isset($o['t'], $o['r'])) {
                if (!isset($declared[$o['t']])) {
                    $r['problems'][] = "row for undeclared table {$o['t']} (line {$n})";
                    break;
                }
                if (count($o['r']) !== $declared[$o['t']]) {
                    $r['problems'][] = "column count mismatch in {$o['t']} (line {$n})";
                    break;
                }
                $seen[$o['t']]++;
                hash_update($hashes[$o['t']], json_encode($o['r'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            }
        }
        fclose($fh);

        if ($header === null || ($header['format'] ?? 0) !== self::FORMAT || ($header['app'] ?? '') !== 'school-erp') {
            $r['problems'][] = 'missing or unsupported header (not a School ERP dump, or a newer format)';
        }
        if ($footer === null) {
            $r['problems'][] = 'footer missing: the file is truncated or incomplete';
        } else {
            foreach ($footer['tables'] as $t => $s) {
                if (($seen[$t] ?? -1) !== $s['rows']) {
                    $r['problems'][] = "row count mismatch for {$t}: file has " . ($seen[$t] ?? 0) . ", footer says {$s['rows']}";
                } elseif (isset($hashes[$t]) && hash_final($hashes[$t]) !== $s['sha256']) {
                    $r['problems'][] = "checksum mismatch for {$t}: content was altered or damaged";
                }
            }
            foreach (array_diff(array_keys($seen), array_keys($footer['tables'])) as $t) {
                $r['problems'][] = "table {$t} is not listed in the footer";
            }
            $r['stats'] = $footer['tables'];
            $r['tables'] = count($footer['tables']);
            $r['rows'] = (int) $footer['total_rows'];
        }
        $r['engine'] = $header['engine'] ?? null;
        $r['created'] = $header['created'] ?? null;
        $r['migrations'] = $header['migrations'] ?? [];
        $r['ok'] = $r['problems'] === [];
        $r['message'] = $r['ok']
            ? sprintf('Valid: %d tables, %s rows, exported from %s on %s. Every checksum matches.', $r['tables'], number_format($r['rows']), $r['engine'], $r['created'])
            : 'INVALID: ' . implode('; ', $r['problems']);
        return $r;
    }

    // ------------------------------------------------------------------ import

    /**
     * Import into a freshly migrated, empty-of-school-data database.
     * Refuses to touch a database that already holds school data.
     *
     * @return array{ok:bool,message:string,imported:array<string,int>,zero_dates:int,orphans:array<string,int>,count_mismatches:string[],problems:string[]}
     */
    public function import(string $path, ?string $passphrase = null): array
    {
        $res = ['ok' => false, 'message' => '', 'imported' => [], 'zero_dates' => 0, 'orphans' => [], 'count_mismatches' => [], 'problems' => []];

        $check = $this->verify($path, $passphrase);
        if (!$check['ok']) {
            $res['message'] = 'Import refused, the backup file is not valid. ' . $check['message'];
            return $res;
        }

        // Target must have the same (or newer) schema and no school data.
        $targetMigrations = array_map('strval', array_column($this->conn->select('SELECT `migration` FROM `migrations`'), 'migration'));
        $missing = array_diff($check['migrations'], $targetMigrations);
        if ($missing !== []) {
            $res['message'] = 'Import refused: the target database has not applied these migrations that the backup was taken with: '
                . implode(', ', $missing) . '. Run the migrations first.';
            return $res;
        }
        $targetTables = $this->schema->tables();
        $counts = $this->schema->rowCounts(array_diff($targetTables, self::SKIP));
        $occupied = [];
        foreach ($counts as $t => $c) {
            if ($c > 0 && !in_array($t, self::MIGRATION_SEEDED, true)) {
                $occupied[] = "{$t} ({$c})";
            }
        }
        if ($occupied !== []) {
            $res['message'] = 'Import refused: the target database already contains data in ' . implode(', ', array_slice($occupied, 0, 8))
                . (count($occupied) > 8 ? ', ...' : '') . '. Import only into a new, empty database (staging), then switch over.';
            return $res;
        }

        $d = $this->conn->dialect();
        $pdo = $this->conn->pdo();
        $fh = $this->open($path, $passphrase, $err);
        if ($fh === null) {
            $res['message'] = $err;
            return $res;
        }

        $this->conn->beginTransaction();
        try {
            // Replace what migrations pre-seeded (children first).
            foreach (['role_permissions', 'permissions', 'id_sequences'] as $t) {
                if (in_array($t, $targetTables, true)) {
                    $pdo->exec('DELETE FROM ' . $d->quote($t));
                }
            }
            $stmt = null;
            $cols = [];
            $table = '';
            while (($raw = fgets($fh)) !== false) {
                $o = json_decode($raw, true);
                if (($o['type'] ?? '') === 'table') {
                    $table = (string) $o['name'];
                    $cols = $o['columns'];
                    if (!in_array($table, $targetTables, true)) {
                        throw new RuntimeException("Target database has no table '{$table}'. Run the migrations first.");
                    }
                    $unknown = array_diff($cols, $this->schema->columns($table));
                    if ($unknown !== []) {
                        throw new RuntimeException("Table '{$table}' in the target lacks columns present in the backup: " . implode(', ', $unknown));
                    }
                    $stmt = $pdo->prepare('INSERT INTO ' . $d->quote($table) . ' (' . implode(',', array_map([$d, 'quote'], $cols)) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')');
                    $res['imported'][$table] = 0;
                } elseif (isset($o['t'], $o['r']) && $stmt !== null) {
                    $vals = [];
                    foreach ($o['r'] as $v) {
                        $v = $this->decodeValue($v);
                        if (is_string($v) && str_starts_with($v, '0000-00-00')) {
                            $v = null; // MySQL zero dates are not valid dates elsewhere
                            $res['zero_dates']++;
                        }
                        $vals[] = $v;
                    }
                    $stmt->execute($vals);
                    $res['imported'][$table]++;
                }
            }
            $this->fixSequences(array_keys($res['imported']));
            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            fclose($fh);
            $res['message'] = 'Import failed and was rolled back; nothing was changed. ' . $this->safeMessage($e);
            return $res;
        }
        fclose($fh);

        // Post-import verification: counts against the footer, and orphaned foreign keys.
        $after = $this->schema->rowCounts(array_keys($check['stats']));
        foreach ($check['stats'] as $t => $s) {
            if (($after[$t] ?? -1) !== $s['rows']) {
                $res['count_mismatches'][] = "{$t}: expected {$s['rows']}, found " . ($after[$t] ?? 'n/a');
            }
        }
        foreach ($this->schema->foreignKeys() as [$ct, $cc, $pt, $pc]) {
            $n = (int) $this->conn->scalar(
                'SELECT COUNT(*) FROM ' . $d->quote($ct) . ' c LEFT JOIN ' . $d->quote($pt) . ' p ON c.' . $d->quote($cc) . ' = p.' . $d->quote($pc)
                . ' WHERE c.' . $d->quote($cc) . ' IS NOT NULL AND p.' . $d->quote($pc) . ' IS NULL'
            );
            if ($n > 0) {
                $res['orphans']["{$ct}.{$cc} -> {$pt}.{$pc}"] = $n;
            }
        }
        $res['ok'] = $res['count_mismatches'] === [] && $res['orphans'] === [];
        $res['message'] = $res['ok']
            ? sprintf('Imported %s rows into %d tables. Row counts match the backup and no foreign key is orphaned.', number_format(array_sum($res['imported'])), count($res['imported']))
            : 'Imported, but verification found problems. Do NOT switch production to this database until they are resolved.';
        return $res;
    }

    // ------------------------------------------------------------------ inspect

    /**
     * Compatibility report for moving this database to another engine.
     *
     * @return array<string,mixed>
     */
    public function inspect(): array
    {
        $tables = array_values(array_diff($this->schema->tables(), self::SKIP));
        $objects = $this->schema->nonPortableObjects();
        $notes = [];
        if ($objects['triggers'] !== []) {
            $notes[] = 'Triggers (' . implode(', ', $objects['triggers']) . ') are not carried in a dump; they are recreated by migrations on the target engine where the database user may create them.';
        }
        foreach (['views', 'routines', 'events'] as $k) {
            if ($objects[$k] !== []) {
                $notes[] = ucfirst($k) . ' (' . implode(', ', $objects[$k]) . ') are NOT portable and will not be migrated. The application does not use them.';
            }
        }
        if (!$this->conn->isPostgres()) {
            $notes[] = 'MySQL/MariaDB text comparison is case-insensitive by collation; PostgreSQL is case-sensitive. The application compensates for search (ILIKE) but unique emails/usernames become case-sensitive on PostgreSQL.';
        }
        return [
            'engine' => $this->conn->engine(), 'server' => $this->schema->serverVersion(),
            'tables' => count($tables), 'rows' => array_sum($this->schema->rowCounts($tables)),
            'non_portable' => $objects, 'notes' => $notes,
        ];
    }

    // ------------------------------------------------------------------ internals

    private function encodeValue(mixed $v): mixed
    {
        if (is_resource($v)) {
            $v = stream_get_contents($v);
        }
        if (is_string($v) && !mb_check_encoding($v, 'UTF-8')) {
            return ['$b64' => base64_encode($v)];
        }
        if (is_bool($v)) {
            return $v ? 1 : 0;
        }
        return $v;
    }

    private function decodeValue(mixed $v): mixed
    {
        if (is_array($v) && isset($v['$b64'])) {
            $bin = base64_decode((string) $v['$b64'], true);
            return $bin === false ? null : $bin;
        }
        return $v;
    }

    /** Reset PostgreSQL identity sequences after explicit-id inserts (MySQL adjusts AUTO_INCREMENT itself). */
    private function fixSequences(array $tables): void
    {
        if (!$this->conn->isPostgres()) {
            return;
        }
        foreach ($tables as $t) {
            $seq = $this->conn->scalar("SELECT pg_get_serial_sequence('\"" . str_replace('"', '', $t) . "\"', 'id')");
            if ($seq) {
                $this->conn->statement('SELECT setval(?, COALESCE((SELECT MAX("id") FROM "' . str_replace('"', '', $t) . '"), 1), (SELECT COUNT(*) > 0 FROM "' . str_replace('"', '', $t) . '"))', [$seq]);
            }
        }
    }

    /** @return resource|null */
    private function open(string $path, ?string $passphrase, ?string &$error = null)
    {
        $error = '';
        if (!is_file($path) || !is_readable($path)) {
            $error = 'Backup file not found or not readable.';
            return null;
        }
        $head = (string) file_get_contents($path, false, null, 0, strlen(self::MAGIC));
        if ($head === self::MAGIC) {
            if ($passphrase === null || $passphrase === '') {
                $error = 'This backup is encrypted; a passphrase is required.';
                return null;
            }
            $plain = $this->decrypt((string) file_get_contents($path), $passphrase);
            if ($plain === null) {
                $error = 'Could not decrypt the backup: wrong passphrase or the file is damaged.';
                return null;
            }
            $tmp = tmpfile();                       // removed automatically when the handle is released
            fwrite($tmp, $plain);
            $this->keep[] = $tmp;
            $gz = @gzopen(stream_get_meta_data($tmp)['uri'], 'rb');
            return $gz ?: null;
        }
        if ((string) file_get_contents($path, false, null, 0, 2) !== "\x1f\x8b") {
            $error = 'Not a School ERP dump (not gzip data).';
            return null;
        }
        $gz = @gzopen($path, 'rb');
        return $gz ?: null;
    }

    /** @var resource[] */
    private array $keep = [];

    private function encrypt(string $plain, string $pass): string
    {
        $salt = random_bytes(16);
        $iv = random_bytes(12);
        $key = hash_pbkdf2('sha256', $pass, $salt, 200000, 32, true);
        $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) {
            throw new RuntimeException('Encryption failed.');
        }
        return self::MAGIC . $salt . $iv . $tag . $ct;
    }

    private function decrypt(string $blob, string $pass): ?string
    {
        $o = strlen(self::MAGIC);
        if (strlen($blob) < $o + 44) {
            return null;
        }
        $salt = substr($blob, $o, 16);
        $iv = substr($blob, $o + 16, 12);
        $tag = substr($blob, $o + 28, 16);
        $ct = substr($blob, $o + 44);
        $key = hash_pbkdf2('sha256', $pass, $salt, 200000, 32, true);
        $plain = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }

    private function safeMessage(\Throwable $e): string
    {
        // Driver messages can echo values; keep only the first line and never any DSN.
        return trim(strtok($e->getMessage(), "\n") ?: 'Unknown error.');
    }
}
