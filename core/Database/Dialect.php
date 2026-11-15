<?php

declare(strict_types=1);

namespace Core\Database;

/**
 * SQL dialect differences in one place.
 *
 * Application SQL is written once in the MySQL/MariaDB flavour the code base
 * already uses (backtick identifiers, `?` placeholders). For PostgreSQL the
 * Connection runs every statement through translate(), which rewrites the
 * handful of constructs that differ. MySQL and MariaDB are returned unchanged.
 *
 * translate() is intentionally narrow. It covers the constructs the
 * application uses; anything else passes through untouched and PostgreSQL
 * will reject it loudly rather than guess.
 */
final class Dialect
{
    /** @var array<string,string> */
    private static array $cache = [];

    public function __construct(private readonly string $engine)
    {
    }

    public function isPostgres(): bool
    {
        return $this->engine === 'pgsql';
    }

    public function name(): string
    {
        return $this->engine;
    }

    public function quote(string $identifier): string
    {
        $id = str_replace(['`', '"'], '', $identifier);
        return $this->isPostgres() ? '"' . $id . '"' : '`' . $id . '`';
    }

    /** MySQL-flavoured SQL -> SQL for this engine. */
    public function translate(string $sql): string
    {
        if (!$this->isPostgres()) {
            return $sql;
        }
        if (isset(self::$cache[$sql])) {
            return self::$cache[$sql];
        }
        $out = $sql;

        // Constructs whose arguments contain literals/parentheses: before the literal-aware pass.
        $out = (string) preg_replace_callback('/DATE_FORMAT\(\s*([^,()]+(?:\([^()]*\))?)\s*,\s*\'([^\']*)\'\s*\)/i', static function (array $m): string {
            $fmt = strtr($m[2], ['%Y' => 'YYYY', '%y' => 'YY', '%m' => 'MM', '%d' => 'DD', '%H' => 'HH24', '%i' => 'MI', '%s' => 'SS', '%M' => 'FMMonth', '%b' => 'Mon']);
            return "to_char({$m[1]}, '{$fmt}')";
        }, $out);
        $out = (string) preg_replace_callback(
            '/CAST\(\s*((?:[^()]|\((?:[^()]|(?-1))*\))*?)\s+AS\s+(UNSIGNED|SIGNED)(?:\s+INTEGER)?\s*\)/i',
            static fn (array $m): string => "CAST(NULLIF(regexp_replace(({$m[1]})::text, '[^0-9]', '', 'g'), '') AS BIGINT)",
            $out
        );

        $out = self::mapOutsideStrings($out, static function (string $c): string {
            $c = str_replace('`', '"', $c);
            $c = (string) preg_replace('/\bLIKE\b/i', 'ILIKE', $c);          // utf8mb4_unicode_ci is case-insensitive
            $c = (string) preg_replace('/\bIFNULL\s*\(/i', 'COALESCE(', $c);
            $c = (string) preg_replace('/\bCURDATE\s*\(\s*\)/i', 'CURRENT_DATE', $c);
            $c = (string) preg_replace('/\bRAND\s*\(\s*\)/i', 'random()', $c);
            $c = (string) preg_replace('/\bDATABASE\s*\(\s*\)/i', 'current_schema()', $c);
            $c = (string) preg_replace('/\bREGEXP\b/i', '~*', $c);
            return $c;
        });

        return self::$cache[$sql] = $out;
    }

    /** SQL fragment: INSERT ... that does nothing on key conflict. */
    public function insertIgnore(string $table, array $columns, string $conflictCols): string
    {
        $cols = implode(',', array_map([$this, 'quote'], $columns));
        $ph = implode(',', array_fill(0, count($columns), '?'));
        $t = $this->quote($table);
        return $this->isPostgres()
            ? "INSERT INTO {$t} ({$cols}) VALUES ({$ph}) ON CONFLICT ({$conflictCols}) DO NOTHING"
            : "INSERT IGNORE INTO {$t} ({$cols}) VALUES ({$ph})";
    }

    /**
     * SQL: insert, or raise `$column` to GREATEST(existing, new) on conflict.
     * Used for monotonic counters (id_sequences, roll_counters).
     *
     * @param string[] $keyCols
     */
    public function upsertGreatest(string $table, array $columns, array $keyCols, string $column): string
    {
        $t = $this->quote($table);
        $cols = implode(',', array_map([$this, 'quote'], $columns));
        $ph = implode(',', array_fill(0, count($columns), '?'));
        $c = $this->quote($column);
        if ($this->isPostgres()) {
            $keys = implode(',', array_map([$this, 'quote'], $keyCols));
            return "INSERT INTO {$t} ({$cols}) VALUES ({$ph}) ON CONFLICT ({$keys}) DO UPDATE SET {$c} = GREATEST({$t}.{$c}, EXCLUDED.{$c})";
        }
        return "INSERT INTO {$t} ({$cols}) VALUES ({$ph}) ON DUPLICATE KEY UPDATE {$c} = GREATEST({$c}, VALUES({$c}))";
    }

    /** SQL: insert; on conflict leave the row untouched (used to create lock anchors). */
    public function upsertNoop(string $table, array $columns, array $keyCols): string
    {
        $t = $this->quote($table);
        $cols = implode(',', array_map([$this, 'quote'], $columns));
        $ph = implode(',', array_fill(0, count($columns), '?'));
        if ($this->isPostgres()) {
            return "INSERT INTO {$t} ({$cols}) VALUES ({$ph}) ON CONFLICT ({$this->keyList($keyCols)}) DO NOTHING";
        }
        $first = $this->quote($columns[0]);
        return "INSERT INTO {$t} ({$cols}) VALUES ({$ph}) ON DUPLICATE KEY UPDATE {$first} = {$first}";
    }

    /** SQL returning one row {c} = 1 when the table exists in the current schema/database. */
    public function tableExistsSql(): string
    {
        return $this->isPostgres()
            ? 'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?'
            : 'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?';
    }

    public function tableCountSql(): string
    {
        return $this->isPostgres()
            ? "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE'"
            : "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'";
    }

    /** SQL returning one column `name` per base table in the current schema/database. */
    public function listTablesSql(): string
    {
        return $this->isPostgres()
            ? "SELECT table_name AS name FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE' ORDER BY table_name"
            : "SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name";
    }

    /** SQL returning `column_name` for a table (placeholder: table name). */
    public function listColumnsSql(): string
    {
        return $this->isPostgres()
            ? 'SELECT column_name AS name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? ORDER BY ordinal_position'
            : 'SELECT column_name AS name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position';
    }

    /**
     * Apply $fn to the parts of $sql that are outside single-quoted string
     * literals (and leave the literals byte-for-byte intact).
     */
    public static function mapOutsideStrings(string $sql, callable $fn): string
    {
        $out = '';
        $buf = '';
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($ch !== "'") {
                $buf .= $ch;
                continue;
            }
            $out .= $fn($buf);
            $buf = '';
            $lit = "'";
            for ($i++; $i < $len; $i++) {
                $lit .= $sql[$i];
                if ($sql[$i] === '\\' && $i + 1 < $len) {
                    $lit .= $sql[++$i];
                } elseif ($sql[$i] === "'") {
                    if (($sql[$i + 1] ?? '') === "'") {
                        $lit .= $sql[++$i];
                    } else {
                        break;
                    }
                }
            }
            $out .= $lit;
        }
        return $out . $fn($buf);
    }

    /** Remove `-- comment` tails (and /* *\/ blocks) that are outside single-quoted literals. */
    public static function stripComments(string $sql): string
    {
        $out = '';
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($c === "'") {
                $out .= $c;
                for ($i++; $i < $len; $i++) {
                    $out .= $sql[$i];
                    if ($sql[$i] === '\\' && $i + 1 < $len) {
                        $out .= $sql[++$i];
                    } elseif ($sql[$i] === "'") {
                        if (($sql[$i + 1] ?? '') === "'") {
                            $out .= $sql[++$i];
                        } else {
                            break;
                        }
                    }
                }
            } elseif ($c === '-' && ($sql[$i + 1] ?? '') === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                $out .= "\n";
            } elseif ($c === '/' && ($sql[$i + 1] ?? '') === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $len : $end + 1;
                $out .= ' ';
            } else {
                $out .= $c;
            }
        }
        return $out;
    }

    /** @param string[] $keys */
    private function keyList(array $keys): string
    {
        return implode(',', array_map([$this, 'quote'], $keys));
    }
}
