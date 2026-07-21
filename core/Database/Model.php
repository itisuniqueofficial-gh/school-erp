<?php

declare(strict_types=1);

namespace Core\Database;

/**
 * Base model providing a thin ActiveRecord layer over the QueryBuilder.
 * Subclasses define $table, $fillable, $primaryKey, and optional $timestamps.
 */
abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected bool $timestamps = true;
    protected bool $softDeletes = false;

    /** @var string[] Mass-assignable attributes. */
    protected array $fillable = [];

    /** @var string[] Attributes hidden from array/JSON output. */
    protected array $hidden = [];

    public function getTable(): string
    {
        return $this->table;
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Start a new query builder scoped to this model's table.
     */
    public function newQuery(): QueryBuilder
    {
        $query = QueryBuilder::table($this->table);
        if ($this->softDeletes) {
            $query->whereNull('deleted_at');
        }
        return $query;
    }

    public static function query(): QueryBuilder
    {
        return (new static())->newQuery();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int|string $id): ?array
    {
        $model = new static();
        return $model->newQuery()->where($model->primaryKey, $id)->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findBy(string $column, mixed $value): ?array
    {
        return static::query()->where($column, $value)->first();
    }

    /**
     * Filter mass-assignable data to the fillable whitelist.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function filterFillable(array $data): array
    {
        if ($this->fillable === []) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Insert a new record and return its id.
     *
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $model = new static();
        $data = $model->filterFillable($data);

        if ($model->timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
        }

        return QueryBuilder::table($model->table)->insert($data);
    }

    /**
     * Update a record by primary key.
     *
     * @param array<string, mixed> $data
     */
    public static function updateById(int|string $id, array $data): int
    {
        $model = new static();
        $data = $model->filterFillable($data);

        if ($model->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        return QueryBuilder::table($model->table)
            ->where($model->primaryKey, $id)
            ->update($data);
    }

    /**
     * Delete a record by primary key (soft delete if enabled).
     */
    public static function deleteById(int|string $id): int
    {
        $model = new static();

        if ($model->softDeletes) {
            return QueryBuilder::table($model->table)
                ->where($model->primaryKey, $id)
                ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        }

        return QueryBuilder::table($model->table)
            ->where($model->primaryKey, $id)
            ->delete();
    }

    /**
     * Paginate results. Returns items plus pagination metadata.
     *
     * @return array{data:array<int,array<string,mixed>>,meta:array<string,int>}
     */
    public static function paginate(int $page = 1, int $perPage = 15, ?QueryBuilder $query = null): array
    {
        $model = new static();
        $query ??= $model->newQuery();

        // Clone-safe count: build a fresh count query with same constraints is
        // complex; instead count via a secondary builder on same table.
        $total = (clone $query)->count();

        $items = $query->forPage(max(1, $page), $perPage)->get();

        return [
            'data' => $items,
            'meta' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) max(1, ceil($total / max(1, $perPage))),
                'from'         => $total ? (($page - 1) * $perPage) + 1 : 0,
                'to'           => min($page * $perPage, $total),
            ],
        ];
    }

    public static function count(): int
    {
        return static::query()->count();
    }
}
