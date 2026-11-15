<?php

declare(strict_types=1);

namespace Core\Database\Schema;

/**
 * Entry point for PHP migrations. It only COLLECTS statements; the Migrator
 * executes them with its normal safety pipeline (ordering, tracking, per-engine
 * translation, transactions where the engine supports DDL transactions).
 *
 *   return [
 *     'up'   => function (Schema $s) { $s->create('library_fines', function (Blueprint $t) { ... }); },
 *     'down' => function (Schema $s) { $s->dropIfEmpty('library_fines'); },
 *   ];
 *
 * There is deliberately no unconditional dropTable(): removing a table that
 * holds rows is data loss and must be an explicit, reviewed decision.
 */
final class Schema
{
    /** @var string[] */
    private array $statements = [];
    /** @var string[] SQL that must return 0 (or no rows) before the migration may run in reverse */
    private array $guards = [];

    /** @param callable(Blueprint):void $define */
    public function create(string $table, callable $define): void
    {
        $bp = new Blueprint($table);
        $define($bp);
        array_push($this->statements, ...$bp->toCreate());
    }

    /** @param callable(Blueprint):void $define adds columns / indexes / foreign keys, or drops columns / indexes */
    public function table(string $table, callable $define): void
    {
        $bp = new Blueprint($table);
        $define($bp);
        array_push($this->statements, ...$bp->toAlter());
    }

    /** Raw MySQL-flavoured statement (translated for PostgreSQL). Use for data seeding the builder cannot express. */
    public function statement(string $sql): void
    {
        $this->statements[] = $sql;
    }

    /**
     * Reverse helper: drop a table, but only when it holds no rows. The guard is
     * evaluated by the Migrator immediately before running the rollback.
     */
    public function dropIfEmpty(string $table): void
    {
        Blueprint::ident($table);
        $this->guards[] = "SELECT COUNT(*) FROM `{$table}`";
        $this->statements[] = "DROP TABLE IF EXISTS `{$table}`";
    }

    /** A rollback is refused unless this query returns 0 (e.g. "no row uses the column being dropped"). */
    public function guard(string $sqlReturningZero): void
    {
        $this->guards[] = $sqlReturningZero;
    }

    /** @return string[] */
    public function statements(): array
    {
        return $this->statements;
    }

    /** @return string[] */
    public function guards(): array
    {
        return $this->guards;
    }
}
