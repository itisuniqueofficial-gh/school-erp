<?php

declare(strict_types=1);

namespace Core\Database\Schema;

/**
 * Declarative table definition. A Blueprint produces MySQL-flavoured DDL, the
 * project's canonical dialect; PostgreSQL gets the same definition through
 * PostgresTranslator, exactly like the SQL migrations. One definition, both engines.
 */
final class Blueprint
{
    /** @var Column[] */
    private array $columns = [];
    /** @var array<int,array{cols:string[],unique:bool,name:?string}> */
    private array $indexes = [];
    /** @var string[] */
    private array $drops = [];
    /** @var string[] */
    private array $dropIndexes = [];
    private bool $softDeletes = false;

    public function __construct(public readonly string $table)
    {
        self::ident($table);
    }

    public static function ident(string $name): string
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,62}$/', $name) !== 1) {
            throw new \InvalidArgumentException("Invalid identifier '{$name}' (lowercase letters, digits and underscores).");
        }
        return $name;
    }

    private function add(string $name, string $type, bool $auto = false): Column
    {
        self::ident($name);
        return $this->columns[] = new Column($name, $type, $auto);
    }

    public function id(string $name = 'id'): Column
    {
        return $this->add($name, 'BIGINT UNSIGNED', true);
    }

    public function foreignId(string $name): Column
    {
        return $this->add($name, 'BIGINT UNSIGNED');
    }

    public function string(string $name, int $length = 255): Column
    {
        return $this->add($name, 'VARCHAR(' . max(1, min(65535, $length)) . ')');
    }

    public function char(string $name, int $length): Column
    {
        return $this->add($name, 'CHAR(' . max(1, $length) . ')');
    }

    public function text(string $name): Column
    {
        return $this->add($name, 'TEXT');
    }

    public function integer(string $name): Column
    {
        return $this->add($name, 'INT');
    }

    public function bigInteger(string $name): Column
    {
        return $this->add($name, 'BIGINT');
    }

    public function smallInteger(string $name): Column
    {
        return $this->add($name, 'SMALLINT');
    }

    public function decimal(string $name, int $total = 10, int $places = 2): Column
    {
        return $this->add($name, "DECIMAL({$total},{$places})");
    }

    /** Stored as a 0/1 small integer on every engine, matching the rest of the schema. */
    public function boolean(string $name): Column
    {
        return $this->add($name, 'TINYINT(1)');
    }

    public function date(string $name): Column
    {
        return $this->add($name, 'DATE');
    }

    public function dateTime(string $name): Column
    {
        return $this->add($name, 'DATETIME');
    }

    public function json(string $name): Column
    {
        return $this->add($name, 'JSON');
    }

    /** @param string[] $values */
    public function enum(string $name, array $values): Column
    {
        foreach ($values as $v) {
            if (preg_match('/^[A-Za-z0-9_ .-]{1,40}$/', (string) $v) !== 1) {
                throw new \InvalidArgumentException('Enum values may contain only letters, digits, space, _ . -');
            }
        }
        return $this->add($name, "ENUM('" . implode("','", $values) . "')");
    }

    /** created_at / updated_at, filled by the application (like every existing table). */
    public function timestamps(): void
    {
        $this->dateTime('created_at')->nullable();
        $this->dateTime('updated_at')->nullable();
    }

    public function softDeletes(): void
    {
        $this->softDeletes = true;
        $this->dateTime('deleted_at')->nullable()->index();
    }

    /** @param string|string[] $cols */
    public function index(string|array $cols, ?string $name = null): void
    {
        $this->indexes[] = ['cols' => (array) $cols, 'unique' => false, 'name' => $name];
    }

    /** @param string|string[] $cols */
    public function unique(string|array $cols, ?string $name = null): void
    {
        $this->indexes[] = ['cols' => (array) $cols, 'unique' => true, 'name' => $name];
    }

    // --- ALTER operations ---------------------------------------------------

    public function dropColumn(string $name): void
    {
        $this->drops[] = self::ident($name);
    }

    public function dropIndex(string $name): void
    {
        $this->dropIndexes[] = self::ident($name);
    }

    // --- SQL generation -----------------------------------------------------

    /** @return string[] */
    public function toCreate(): array
    {
        $defs = [];
        $keys = [];
        $fks = [];
        $pk = [];
        foreach ($this->columns as $c) {
            $defs[] = $this->columnSql($c);
            if ($c->autoIncrement) {
                $pk[] = $c->name;
            }
            if ($c->unique) {
                $keys[] = "UNIQUE KEY `uq_{$this->table}_{$c->name}` (`{$c->name}`)";
            }
            if ($c->index && !$c->unique) {
                $keys[] = "KEY `idx_{$this->table}_{$c->name}` (`{$c->name}`)";
            }
            if ($c->referencesTable !== null) {
                $keys[] = "KEY `idx_{$this->table}_{$c->name}` (`{$c->name}`)";
                $fks[] = sprintf('CONSTRAINT `fk_%s_%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s',
                    $this->table, $c->name, $c->name, $c->referencesTable, $c->referencesColumn, $c->onDelete);
            }
        }
        foreach ($this->indexes as $i) {
            $name = $i['name'] ?? (($i['unique'] ? 'uq_' : 'idx_') . $this->table . '_' . implode('_', $i['cols']));
            $keys[] = ($i['unique'] ? 'UNIQUE KEY' : 'KEY') . " `" . self::ident($name) . '` (' . $this->cols($i['cols']) . ')';
        }
        if ($pk !== []) {
            array_unshift($keys, 'PRIMARY KEY (' . $this->cols($pk) . ')');
        }
        $body = implode(",\n    ", array_merge($defs, $keys, $fks));
        return ["CREATE TABLE IF NOT EXISTS `{$this->table}` (\n    {$body}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC"];
    }

    /** @return string[] */
    public function toAlter(): array
    {
        $out = [];
        $t = "`{$this->table}`";
        foreach ($this->columns as $c) {
            $out[] = "ALTER TABLE {$t} ADD COLUMN " . $this->columnSql($c);
            if ($c->unique) {
                $out[] = "CREATE UNIQUE INDEX `uq_{$this->table}_{$c->name}` ON {$t} (`{$c->name}`)";
            } elseif ($c->index || $c->referencesTable !== null) {
                $out[] = "CREATE INDEX `idx_{$this->table}_{$c->name}` ON {$t} (`{$c->name}`)";
            }
            if ($c->referencesTable !== null) {
                $out[] = sprintf("ALTER TABLE {$t} ADD CONSTRAINT `fk_%s_%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s",
                    $this->table, $c->name, $c->name, $c->referencesTable, $c->referencesColumn, $c->onDelete);
            }
        }
        foreach ($this->indexes as $i) {
            $name = $i['name'] ?? (($i['unique'] ? 'uq_' : 'idx_') . $this->table . '_' . implode('_', $i['cols']));
            $out[] = 'CREATE ' . ($i['unique'] ? 'UNIQUE ' : '') . 'INDEX `' . self::ident($name) . "` ON {$t} (" . $this->cols($i['cols']) . ')';
        }
        foreach ($this->dropIndexes as $n) {
            $out[] = "ALTER TABLE {$t} DROP INDEX `{$n}`";
        }
        foreach ($this->drops as $c) {
            $out[] = "ALTER TABLE {$t} DROP COLUMN `{$c}`";
        }
        return $out;
    }

    private function columnSql(Column $c): string
    {
        $sql = '`' . $c->name . '` ' . $c->type;
        if ($c->autoIncrement) {
            return $sql . ' NOT NULL AUTO_INCREMENT';
        }
        $sql .= $c->nullable ? ' NULL' : ' NOT NULL';
        if ($c->hasDefault) {
            $sql .= ' DEFAULT ' . $this->literal($c->default);
        } elseif ($c->nullable) {
            $sql .= ' DEFAULT NULL';
        }
        return $sql;
    }

    private function literal(mixed $v): string
    {
        return match (true) {
            $v === null => 'NULL',
            is_bool($v) => $v ? '1' : '0',
            is_int($v) || is_float($v) => (string) $v,
            $v === 'CURRENT_TIMESTAMP' => 'CURRENT_TIMESTAMP',
            default => "'" . str_replace("'", "''", (string) $v) . "'",
        };
    }

    /** @param string[] $cols */
    private function cols(array $cols): string
    {
        return implode(', ', array_map(static fn ($c) => '`' . self::ident($c) . '`', $cols));
    }
}
