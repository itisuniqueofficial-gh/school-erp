<?php

declare(strict_types=1);

namespace Core\Database;

/**
 * Read-only schema introspection that works on MySQL, MariaDB and PostgreSQL
 * (information_schema + the few engine-specific catalogs needed).
 */
final class SchemaInspector
{
    public function __construct(private readonly Connection $conn)
    {
    }

    public static function make(): self
    {
        return new self(Connection::instance());
    }

    /** @return string[] base tables of the current database/schema */
    public function tables(): array
    {
        return array_map('strval', array_column($this->conn->select($this->conn->dialect()->listTablesSql()), 'name'));
    }

    /**
     * Columns that can be written (generated columns are excluded: the
     * database recomputes them).
     *
     * @return string[]
     */
    public function writableColumns(string $table): array
    {
        if ($this->conn->isPostgres()) {
            $rows = $this->conn->select(
                "SELECT column_name AS name FROM information_schema.columns
                  WHERE table_schema = current_schema() AND table_name = ? AND is_generated <> 'ALWAYS'
                  ORDER BY ordinal_position",
                [$table]
            );
        } else {
            $rows = $this->conn->select(
                "SELECT column_name AS name FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = ? AND extra NOT LIKE '%GENERATED%'
                  ORDER BY ordinal_position",
                [$table]
            );
        }
        return array_map('strval', array_column($rows, 'name'));
    }

    /** @return string[] */
    public function columns(string $table): array
    {
        return array_map('strval', array_column($this->conn->select($this->conn->dialect()->listColumnsSql(), [$table]), 'name'));
    }

    /**
     * Foreign keys as [child_table, child_column, parent_table, parent_column].
     *
     * @return array<int,array{0:string,1:string,2:string,3:string}>
     */
    public function foreignKeys(): array
    {
        if ($this->conn->isPostgres()) {
            $rows = $this->conn->select(
                "SELECT kcu.table_name AS ct, kcu.column_name AS cc, ccu.table_name AS pt, ccu.column_name AS pc
                   FROM information_schema.table_constraints tc
                   JOIN information_schema.key_column_usage kcu ON kcu.constraint_name = tc.constraint_name AND kcu.table_schema = tc.table_schema AND kcu.table_name = tc.table_name
                   JOIN information_schema.constraint_column_usage ccu ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema
                  WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = current_schema()"
            );
        } else {
            $rows = $this->conn->select(
                'SELECT table_name AS ct, column_name AS cc, referenced_table_name AS pt, referenced_column_name AS pc
                   FROM information_schema.key_column_usage
                  WHERE table_schema = DATABASE() AND referenced_table_name IS NOT NULL'
            );
        }
        return array_map(static fn ($r) => [(string) $r['ct'], (string) $r['cc'], (string) $r['pt'], (string) $r['pc']], $rows);
    }

    /**
     * Tables ordered parents-first so rows can be inserted without deferring
     * foreign keys. Tables in a dependency cycle are appended at the end.
     *
     * @param string[] $tables
     * @return string[]
     */
    public function insertOrder(array $tables): array
    {
        $deps = array_fill_keys($tables, []);
        foreach ($this->foreignKeys() as [$child, , $parent]) {
            if ($child !== $parent && isset($deps[$child], $deps[$parent])) {
                $deps[$child][$parent] = true;
            }
        }
        $order = [];
        while ($deps !== []) {
            $ready = array_keys(array_filter($deps, static fn ($d) => $d === []));
            if ($ready === []) {
                array_push($order, ...array_keys($deps));
                break;
            }
            sort($ready);
            foreach ($ready as $t) {
                $order[] = $t;
                unset($deps[$t]);
            }
            foreach ($deps as $t => $d) {
                foreach ($ready as $r) {
                    unset($deps[$t][$r]);
                }
            }
        }
        return $order;
    }

    /** @return array<string,int> row count per table */
    public function rowCounts(array $tables): array
    {
        $out = [];
        $d = $this->conn->dialect();
        foreach ($tables as $t) {
            $out[$t] = (int) $this->conn->scalar('SELECT COUNT(*) FROM ' . $d->quote($t));
        }
        return $out;
    }

    /**
     * Database objects that do not travel in a portable dump.
     *
     * @return array<string,string[]> kind => names
     */
    public function nonPortableObjects(): array
    {
        $out = ['triggers' => [], 'views' => [], 'routines' => [], 'events' => []];
        if ($this->conn->isPostgres()) {
            $out['triggers'] = array_column($this->conn->select("SELECT DISTINCT trigger_name AS n FROM information_schema.triggers WHERE trigger_schema = current_schema()"), 'n');
            $out['views'] = array_column($this->conn->select("SELECT table_name AS n FROM information_schema.views WHERE table_schema = current_schema()"), 'n');
            $out['routines'] = array_column($this->conn->select("SELECT routine_name AS n FROM information_schema.routines WHERE routine_schema = current_schema()"), 'n');
        } else {
            $out['triggers'] = array_column($this->conn->select('SELECT trigger_name AS n FROM information_schema.triggers WHERE trigger_schema = DATABASE()'), 'n');
            $out['views'] = array_column($this->conn->select('SELECT table_name AS n FROM information_schema.views WHERE table_schema = DATABASE()'), 'n');
            $out['routines'] = array_column($this->conn->select('SELECT routine_name AS n FROM information_schema.routines WHERE routine_schema = DATABASE()'), 'n');
            $out['events'] = array_column($this->conn->select('SELECT event_name AS n FROM information_schema.events WHERE event_schema = DATABASE()'), 'n');
        }
        return $out;
    }

    /**
     * Column facts: table => column => nullable.
     *
     * @return array<string,array<string,bool>>
     */
    public function columnMap(): array
    {
        $sql = $this->conn->isPostgres()
            ? "SELECT c.table_name AS t, c.column_name AS c, c.is_nullable AS n FROM information_schema.columns c
                 JOIN information_schema.tables x ON x.table_schema = c.table_schema AND x.table_name = c.table_name AND x.table_type = 'BASE TABLE'
                WHERE c.table_schema = current_schema()"
            : "SELECT c.table_name AS t, c.column_name AS c, c.is_nullable AS n FROM information_schema.columns c
                 JOIN information_schema.tables x ON x.table_schema = c.table_schema AND x.table_name = c.table_name AND x.table_type = 'BASE TABLE'
                WHERE c.table_schema = DATABASE()";
        $out = [];
        foreach ($this->conn->select($sql) as $r) {
            $out[strtolower((string) $r['t'])][strtolower((string) $r['c'])] = strtoupper((string) $r['n']) === 'YES';
        }
        return $out;
    }

    /**
     * Unique indexes (excluding the primary key) as sorted column lists.
     *
     * @return array<string,string[]> table => list of "col1,col2" keys
     */
    public function uniqueSets(): array
    {
        if ($this->conn->isPostgres()) {
            $rows = $this->conn->select(
                "SELECT t.relname AS t, i.relname AS i, a.attname AS c
                   FROM pg_index ix JOIN pg_class t ON t.oid = ix.indrelid JOIN pg_class i ON i.oid = ix.indexrelid
                   JOIN pg_namespace n ON n.oid = t.relnamespace
                   JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
                  WHERE ix.indisunique AND NOT ix.indisprimary AND n.nspname = current_schema()"
            );
        } else {
            $rows = $this->conn->select(
                "SELECT table_name AS t, index_name AS i, column_name AS c FROM information_schema.statistics
                  WHERE table_schema = DATABASE() AND non_unique = 0 AND index_name <> 'PRIMARY'"
            );
        }
        $by = [];
        foreach ($rows as $r) {
            $by[strtolower((string) $r['t'])][(string) $r['i']][] = strtolower((string) $r['c']);
        }
        $out = [];
        foreach ($by as $t => $idx) {
            foreach ($idx as $cols) {
                sort($cols);
                $out[$t][] = implode(',', $cols);
            }
            $out[$t] = array_values(array_unique($out[$t]));
            sort($out[$t]);
        }
        return $out;
    }

    /**
     * Foreign keys with their delete rule.
     *
     * @return array<int,array{table:string,column:string,ref_table:string,ref_column:string,on_delete:string}>
     */
    public function foreignKeyRules(): array
    {
        if ($this->conn->isPostgres()) {
            $rows = $this->conn->select(
                "SELECT kcu.table_name AS t, kcu.column_name AS c, ccu.table_name AS pt, ccu.column_name AS pc, rc.delete_rule AS d
                   FROM information_schema.referential_constraints rc
                   JOIN information_schema.key_column_usage kcu ON kcu.constraint_name = rc.constraint_name AND kcu.constraint_schema = rc.constraint_schema
                   JOIN information_schema.constraint_column_usage ccu ON ccu.constraint_name = rc.constraint_name AND ccu.constraint_schema = rc.constraint_schema
                  WHERE rc.constraint_schema = current_schema()"
            );
        } else {
            $rows = $this->conn->select(
                'SELECT kcu.table_name AS t, kcu.column_name AS c, kcu.referenced_table_name AS pt, kcu.referenced_column_name AS pc, rc.delete_rule AS d
                   FROM information_schema.key_column_usage kcu
                   JOIN information_schema.referential_constraints rc ON rc.constraint_name = kcu.constraint_name AND rc.constraint_schema = kcu.table_schema AND rc.table_name = kcu.table_name
                  WHERE kcu.table_schema = DATABASE() AND kcu.referenced_table_name IS NOT NULL'
            );
        }
        return array_map(static fn ($r) => ['table' => strtolower((string) $r['t']), 'column' => strtolower((string) $r['c']),
            'ref_table' => strtolower((string) $r['pt']), 'ref_column' => strtolower((string) $r['pc']), 'on_delete' => strtoupper((string) $r['d'])], $rows);
    }

    /** @return string server version string, e.g. "8.4.13" or "PostgreSQL 16.4" */
    public function serverVersion(): string
    {
        return (string) $this->conn->scalar('SELECT VERSION()');
    }
}
