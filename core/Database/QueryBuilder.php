<?php

declare(strict_types=1);

namespace Core\Database;

/**
 * Fluent SQL query builder producing parameterized statements.
 * Identifiers are quoted; all values are bound to prevent SQL injection.
 */
final class QueryBuilder
{
    private string $table;
    private string $columns = '*';

    /** @var array<int, array{0:string,1:string,2:mixed,3:string}> */
    private array $wheres = [];

    /** @var array<int, string> */
    private array $orders = [];

    /** @var array<int, array{0:string,1:string,2:string}> */
    private array $joins = [];

    /** @var array<int, string> */
    private array $groups = [];

    private ?string $having = null;

    private ?int $limit = null;
    private ?int $offset = null;

    /** @var array<int, mixed> */
    private array $bindings = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public static function table(string $table): self
    {
        return new self($table);
    }

    /**
     * @param string|string[] $columns
     */
    public function select(string|array $columns = ['*']): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        $this->columns = implode(', ', array_map([$this, 'wrap'], $columns));
        return $this;
    }

    public function selectRaw(string $expression): self
    {
        $this->columns = $expression;
        return $this;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): self
    {
        // Support two-argument form: where('col', value) => equals.
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = [$column, (string) $operator, $value, $boolean];
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        return $this->where($column, (string) $operator, $value, 'OR');
    }

    /**
     * @param array<int, mixed> $values
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        if ($values === []) {
            // Guarantee no rows.
            $this->wheres[] = ['__raw__', '', '1 = 0', $boolean];
            return $this;
        }
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = ['__in__', $column, $placeholders, $boolean];
        foreach ($values as $v) {
            $this->bindings[] = $v;
        }
        return $this;
    }

    public function whereNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = ['__null__', $column, null, $boolean];
        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = ['__notnull__', $column, null, $boolean];
        return $this;
    }

    public function whereLike(string $column, string $value, string $boolean = 'AND'): self
    {
        $this->wheres[] = [$column, 'LIKE', $value, $boolean];
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Add a raw (already-grouped) WHERE condition with its bindings.
     * Use for parenthesised OR groups, e.g. "(a LIKE ? OR b LIKE ?)".
     *
     * @param array<int,mixed> $bindings
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        $this->wheres[] = ['__raw__', '', $sql, $boolean];
        foreach ($bindings as $b) {
            $this->bindings[] = $b;
        }
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [$type, $table, "{$this->wrap($first)} {$operator} {$this->wrap($second)}"];
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = "{$this->wrap($column)} {$direction}";
        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $c) {
            $this->groups[] = $this->wrap($c);
        }
        return $this;
    }

    public function having(string $expression): self
    {
        $this->having = $expression;
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    public function forPage(int $page, int $perPage): self
    {
        return $this->limit($perPage)->offset(($page - 1) * $perPage);
    }

    // --- Execution ---------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public function get(): array
    {
        return Connection::instance()->select($this->toSql(), $this->bindings);
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    public function value(string $column): mixed
    {
        // Respect any existing SELECT (e.g. selectRaw aggregates). If none was
        // set, the default '*' still lets us read the requested column.
        $row = $this->first();
        return $row[$column] ?? null;
    }

    public function count(): int
    {
        $sql = $this->buildSelect('COUNT(*) AS aggregate');
        $row = Connection::instance()->selectOne($sql, $this->bindings);
        return (int) ($row['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $wrapped = implode(', ', array_map([$this, 'wrap'], $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO {$this->wrap($this->table)} ({$wrapped}) VALUES ({$placeholders})";
        Connection::instance()->affectingStatement($sql, array_values($data));
        return (int) Connection::instance()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(array $data): int
    {
        $sets = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            $sets[] = "{$this->wrap($column)} = ?";
            $bindings[] = $value;
        }
        $sql = "UPDATE {$this->wrap($this->table)} SET " . implode(', ', $sets);
        $sql .= $this->compileWheres();
        $bindings = array_merge($bindings, $this->bindings);
        return Connection::instance()->affectingStatement($sql, $bindings);
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->wrap($this->table)}" . $this->compileWheres();
        return Connection::instance()->affectingStatement($sql, $this->bindings);
    }

    public function toSql(): string
    {
        return $this->buildSelect($this->columns);
    }

    // --- Compilation -------------------------------------------------------

    private function buildSelect(string $columns): string
    {
        $sql = "SELECT {$columns} FROM {$this->wrap($this->table)}";

        foreach ($this->joins as [$type, $table, $condition]) {
            $sql .= " {$type} JOIN {$this->wrap($table)} ON {$condition}";
        }

        $sql .= $this->compileWheres();

        if ($this->groups !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }
        if ($this->having !== null) {
            $sql .= ' HAVING ' . $this->having;
        }
        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    private function compileWheres(): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $clauses = [];
        foreach ($this->wheres as $i => [$column, $operator, $value, $boolean]) {
            $prefix = $i === 0 ? '' : " {$boolean} ";

            $clause = match ($column) {
                '__raw__'    => $value,
                '__in__'     => "{$this->wrap($operator)} IN ({$value})",
                '__null__'   => "{$this->wrap($operator)} IS NULL",
                '__notnull__'=> "{$this->wrap($operator)} IS NOT NULL",
                default      => "{$this->wrap($column)} {$operator} ?",
            };

            $clauses[] = $prefix . $clause;
        }

        return ' WHERE ' . implode('', $clauses);
    }

    /**
     * Quote an identifier safely. Supports table.column and aliases.
     */
    private function wrap(string $value): string
    {
        // Leave expressions/aggregates untouched.
        if (str_contains($value, '(') || $value === '*') {
            return $value;
        }

        // Handle "column AS alias".
        if (stripos($value, ' as ') !== false) {
            [$col, $alias] = preg_split('/\s+as\s+/i', $value, 2) ?: [$value, ''];
            return $this->wrap(trim($col)) . ' AS ' . $this->wrapSegment(trim($alias));
        }

        $segments = explode('.', $value);
        return implode('.', array_map([$this, 'wrapSegment'], $segments));
    }

    private function wrapSegment(string $segment): string
    {
        $segment = trim($segment);
        if ($segment === '*') {
            return '*';
        }
        // Strip any existing backticks then re-wrap; whitelist safe chars.
        $segment = str_replace('`', '', $segment);
        return '`' . $segment . '`';
    }
}
