<?php

declare(strict_types=1);

namespace App\Services\Database;

use Core\Database\Connection;
use Core\Database\SchemaInspector;

/**
 * The expected structure of a correctly migrated database, kept in
 * database/schema-manifest.json (engine-neutral: tables, columns with
 * nullability, unique key column sets, foreign keys with delete rules).
 *
 * Migrations stay the single source of truth for how the schema is built; the
 * manifest is the reviewed snapshot of what that produces. validate() compares a
 * live database against it and reports drift (missing table/column, nullability,
 * missing unique key, missing or weakened foreign key). Extra objects added by an
 * administrator are never an error. Regenerate after adding migrations:
 *   php bin/console db:schema:dump
 */
final class SchemaManifest
{
    public function __construct(private readonly ?Connection $conn = null, private readonly ?string $path = null)
    {
    }

    public function path(): string
    {
        return $this->path ?? dirname(__DIR__, 3) . '/database/schema-manifest.json';
    }

    /** @return array<string,mixed> */
    public function build(string $version): array
    {
        $ins = new SchemaInspector($this->conn ?? Connection::instance());
        $cols = $ins->columnMap();
        $uniq = $ins->uniqueSets();
        $fks = [];
        foreach ($ins->foreignKeyRules() as $f) {
            $fks[$f['table']][] = ['column' => $f['column'], 'ref' => $f['ref_table'] . '.' . $f['ref_column'], 'on_delete' => $f['on_delete']];
        }
        $tables = [];
        ksort($cols);
        foreach ($cols as $t => $c) {
            if ($t === 'migrations' || $t === 'migration_log') {
                continue;
            }
            ksort($c);
            $f = $fks[$t] ?? [];
            usort($f, static fn ($a, $b) => [$a['column'], $a['ref']] <=> [$b['column'], $b['ref']]);
            $tables[$t] = ['columns' => array_map(static fn ($n) => ['null' => $n], $c), 'unique' => $uniq[$t] ?? [], 'foreign_keys' => $f];
        }
        return ['format' => 1, 'schema_version' => $version, 'tables' => $tables];
    }

    public function dump(string $version): int
    {
        $json = json_encode($this->build($version), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        file_put_contents($this->path(), $json, LOCK_EX);
        return strlen($json);
    }

    /** @return array<string,mixed>|null */
    public function load(): ?array
    {
        $p = $this->path();
        $d = is_file($p) ? json_decode((string) file_get_contents($p), true) : null;
        return is_array($d) && isset($d['tables']) ? $d : null;
    }

    /**
     * @return array{ok:bool,checked:bool,message:string,version:?string,issues:array<int,array{type:string,table:string,detail:string}>}
     */
    public function validate(?string $dbVersion = null): array
    {
        $m = $this->load();
        if ($m === null) {
            return ['ok' => true, 'checked' => false, 'message' => 'No schema manifest found; structure was not compared.', 'version' => null, 'issues' => []];
        }
        // A database newer than the manifest has objects the manifest does not know about. Compare only what is known.
        $ins = new SchemaInspector($this->conn ?? Connection::instance());
        $cols = $ins->columnMap();
        $uniq = $ins->uniqueSets();
        $fkLive = [];
        foreach ($ins->foreignKeyRules() as $f) {
            $fkLive[$f['table']][$f['column'] . '>' . $f['ref_table'] . '.' . $f['ref_column']] = $f['on_delete'];
        }
        $issues = [];
        foreach ($m['tables'] as $t => $def) {
            if (!isset($cols[$t])) {
                $issues[] = ['type' => 'missing_table', 'table' => $t, 'detail' => "table {$t} does not exist"];
                continue;
            }
            foreach ($def['columns'] as $c => $meta) {
                if (!isset($cols[$t][$c])) {
                    $issues[] = ['type' => 'missing_column', 'table' => $t, 'detail' => "column {$t}.{$c} does not exist"];
                } elseif ($cols[$t][$c] !== (bool) $meta['null']) {
                    $issues[] = ['type' => 'nullability', 'table' => $t, 'detail' => "{$t}.{$c} should be " . ($meta['null'] ? 'NULL' : 'NOT NULL')];
                }
            }
            foreach ($def['unique'] as $u) {
                if (!in_array($u, $uniq[$t] ?? [], true)) {
                    $issues[] = ['type' => 'missing_unique', 'table' => $t, 'detail' => "unique key on ({$u}) is missing"];
                }
            }
            foreach ($def['foreign_keys'] as $fk) {
                $key = $fk['column'] . '>' . $fk['ref'];
                if (!isset($fkLive[$t][$key])) {
                    $issues[] = ['type' => 'missing_fk', 'table' => $t, 'detail' => "foreign key {$t}.{$fk['column']} -> {$fk['ref']} is missing"];
                } elseif ($fkLive[$t][$key] !== $fk['on_delete']) {
                    $issues[] = ['type' => 'fk_rule', 'table' => $t, 'detail' => "foreign key {$t}.{$fk['column']} has ON DELETE {$fkLive[$t][$key]}, expected {$fk['on_delete']}"];
                }
            }
        }
        $n = count($m['tables']);
        return ['ok' => $issues === [], 'checked' => true, 'version' => $m['schema_version'] ?? null, 'issues' => $issues,
            'message' => $issues === [] ? "Schema matches the manifest ({$n} tables, structure of version " . ($m['schema_version'] ?? '?') . ').'
                : count($issues) . ' difference(s) from the expected schema.'];
    }
}
