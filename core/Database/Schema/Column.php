<?php

declare(strict_types=1);

namespace Core\Database\Schema;

/** One column definition of a Blueprint (fluent modifiers). */
final class Column
{
    public bool $nullable = false;
    public bool $hasDefault = false;
    public mixed $default = null;
    public bool $unique = false;
    public bool $index = false;
    public ?string $referencesTable = null;
    public string $referencesColumn = 'id';
    public string $onDelete = 'RESTRICT';

    public function __construct(public readonly string $name, public readonly string $type, public readonly bool $autoIncrement = false)
    {
    }

    public function nullable(bool $v = true): self
    {
        $this->nullable = $v;
        return $this;
    }

    public function default(mixed $v): self
    {
        $this->hasDefault = true;
        $this->default = $v;
        return $this;
    }

    public function unique(): self
    {
        $this->unique = true;
        return $this;
    }

    public function index(): self
    {
        $this->index = true;
        return $this;
    }

    /**
     * Foreign key to $table.$column. Deletion is RESTRICTED unless stated:
     * history (students, payments, marks...) must never vanish as a side effect.
     */
    public function constrained(string $table, string $column = 'id'): self
    {
        $this->referencesTable = $table;
        $this->referencesColumn = $column;
        return $this;
    }

    /** @param 'restrict'|'cascade'|'set null'|'no action' $rule */
    public function onDelete(string $rule): self
    {
        $rule = strtoupper($rule);
        if (!in_array($rule, ['RESTRICT', 'CASCADE', 'SET NULL', 'NO ACTION'], true)) {
            throw new \InvalidArgumentException('Invalid ON DELETE rule.');
        }
        if ($rule === 'SET NULL') {
            $this->nullable = true;
        }
        $this->onDelete = $rule;
        return $this;
    }
}
