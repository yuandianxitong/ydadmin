<?php

declare(strict_types=1);

namespace core\base;

use Illuminate\Database\Eloquent\Builder;

/**
 * 数据访问层基类：唯一与 Model 交互的层。
 *
 * 所有查询必须经 query() 起手，禁止 $this->model->where()——M1 会在 query() 里注入
 * 数据权限条件（spec §5），绕开它就等于绕开数据权限。
 * 底层异常不在这里包装，直接上抛给 Handler（避免把 SQL 错误原文返回给客户端）。
 */
abstract class Repository
{
    protected Model $model;

    public function __construct()
    {
        $this->model = $this->getModel();
    }

    abstract protected function getModel(): Model;

    /** @return Builder<Model> */
    protected function query(): Builder
    {
        return $this->model->newQuery();
    }

    /** @return array<string, mixed>|null */
    public function find(int|string $id): ?array
    {
        return $this->query()->where($this->model->getKeyName(), $id)->first()?->toArray();
    }

    /**
     * @param array<string, mixed> $where
     * @return array<string, mixed>|null
     */
    public function findWhere(array $where): ?array
    {
        return $this->query()->where($where)->first()?->toArray();
    }

    /**
     * 插入不经 query()——查询条件对 INSERT 不起作用；M1 数据权限在本方法内显式填入
     * created_by（spec §5.4），不要把 scope 逻辑加到这里的查询构造上。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        return $this->model->newQuery()->create($data)->toArray();
    }

    /** @param array<string, mixed> $data */
    public function update(int|string $id, array $data): bool
    {
        return $this->query()->where($this->model->getKeyName(), $id)->update($data) > 0;
    }

    /**
     * @param array<string, mixed> $where
     * @param array<string, mixed> $data
     */
    public function updateWhere(array $where, array $data): int
    {
        return $this->query()->where($where)->update($data);
    }

    /** 经模型实例 delete()，让 SoftDeletes 等模型事件正确触发。 */
    public function delete(int|string $id): bool
    {
        $instance = $this->query()->where($this->model->getKeyName(), $id)->first();

        return $instance !== null && (bool) $instance->delete();
    }

    /** @param array<string, mixed> $where */
    public function deleteWhere(array $where): int
    {
        $count = 0;
        foreach ($this->query()->where($where)->get() as $instance) {
            $count += (int) $instance->delete();
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $where
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getList(array $where = [], int $page = 1, int $limit = 15, string $order = 'id desc'): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $query = $this->query()->where($where);
        $total = (clone $query)->count();
        $list = $this->applyOrder($query, $order)->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * @param array<string, mixed> $where
     * @return array<int, array<string, mixed>>
     */
    public function getAll(array $where = [], string $order = 'id desc'): array
    {
        return $this->applyOrder($this->query()->where($where), $order)->get()->toArray();
    }

    /** @param array<string, mixed> $where */
    public function count(array $where = []): int
    {
        return $this->query()->where($where)->count();
    }

    /** @param array<string, mixed> $where */
    public function exists(array $where): bool
    {
        return $this->query()->where($where)->exists();
    }

    /** @param array<string, mixed> $where */
    public function value(array $where, string $field): mixed
    {
        return $this->query()->where($where)->value($field);
    }

    /**
     * @param array<string, mixed> $where
     * @return array<int|string, mixed>
     */
    public function column(array $where, string $field, string $key = ''): array
    {
        return $this->query()->where($where)->pluck($field, $key !== '' ? $key : null)->all();
    }

    /** @param array<string, mixed> $where */
    public function inc(array $where, string $field, int $step = 1): int
    {
        return (int) $this->query()->where($where)->increment($field, $step);
    }

    /** @param array<string, mixed> $where */
    public function dec(array $where, string $field, int $step = 1): int
    {
        return (int) $this->query()->where($where)->decrement($field, $step);
    }

    /**
     * 解析 'sort asc, id desc' 形式的排序串；方向不是 asc/desc 时按 asc 处理。
     *
     * @param Builder<Model> $query
     * @return Builder<Model>
     */
    protected function applyOrder(Builder $query, string $order): Builder
    {
        foreach (explode(',', $order) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            [$column, $direction] = array_pad(preg_split('/\s+/', $segment, 2) ?: [], 2, 'asc');
            $query->orderBy((string) $column, strtolower((string) $direction) === 'desc' ? 'desc' : 'asc');
        }

        return $query;
    }

    /**
     * @param array<int, array<string, mixed>> $list
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    protected function buildPagination(array $list, int $page, int $limit, int $total): array
    {
        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => max(1, (int) ceil($total / $limit)),
            ],
        ];
    }
}
