<?php

declare(strict_types=1);

namespace core\base;

use core\context\RequestContext;
use core\datascope\DataScope;
use core\datascope\DataScopeScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * 数据访问层基类：唯一与 Model 交互的层。
 *
 * 所有查询必须经 query() 起手，禁止 $this->model->where()——query() 在受控表上以全局作用域挂上
 * 数据权限条件（core\datascope\DataScopeScope，spec §5），绕开它就等于绕开数据权限。
 * 底层异常不在这里包装，直接上抛给 Handler（避免把 SQL 错误原文返回给客户端）。
 */
abstract class Repository
{
    /** 分页每页上限（spec §1.2：前端没有超过 100 的取数，超出按 100 截断）。 */
    public const MAX_PAGE_SIZE = 100;

    /**
     * 可排序列白名单。排序串可能来自请求，白名单外的列一律拒绝；子类按表覆盖。
     *
     * @var list<string>
     */
    protected array $sortable = ['id', 'sort', 'created_at', 'updated_at'];

    /** 是否受数据权限约束（spec §5.2）。 */
    protected bool $dataScoped = false;

    /** 数据归属人列（「仅本人」按它过滤；未配置部门列时经它反查 admins.department_id）。 */
    protected string $ownerColumn = 'created_by';

    /** 部门列；配置后「部门」类范围直接按它过滤。 */
    protected ?string $deptColumn = null;

    /**
     * 创建人列：受控表 create() 时数据未提供就填当前管理员（spec §5.2）。表里没有这一列时设为 null
     * （如登录日志、操作日志：归属人是 admin_id，没有 created_by）。
     */
    protected ?string $creatorColumn = 'created_by';

    protected Model $model;

    public function __construct()
    {
        $this->model = $this->getModel();
    }

    abstract protected function getModel(): Model;

    /**
     * 受控表且当前快照不是「全部」时，给这条查询挂上数据权限全局作用域（快照此刻取定，列名带表名前缀）。
     * 只作用于根表：with() 预加载不再过滤；不要用 whereHas() 伸入另一张受控表做访问控制。
     *
     * @return Builder<Model>
     */
    protected function query(): Builder
    {
        $query = $this->model->newQuery();
        if ($this->dataScoped) {
            $snapshot = DataScope::current();
            if ($snapshot !== null && !$snapshot->all) {
                $query->withGlobalScope(DataScopeScope::class, new DataScopeScope(
                    $snapshot,
                    $this->qualify($this->ownerColumn),
                    $this->deptColumn !== null ? $this->qualify($this->deptColumn) : null,
                ));
            }
        }

        return $query;
    }

    /** @return array<string, mixed>|null */
    public function find(int|string $id): ?array
    {
        return $this->query()->where($this->model->getQualifiedKeyName(), $id)->first()?->toArray();
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
     * 插入不经 query()——作用域对 INSERT 不起作用；受控表在数据未提供时自动填 $creatorColumn（spec §5.2），
     * 该列为 null 的仓储不填。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $creator = $this->creatorColumn;
        if ($this->dataScoped && $creator !== null && !array_key_exists($creator, $data) && RequestContext::actingUser() > 0) {
            $data[$creator] = RequestContext::actingUser();
        }

        return $this->model->newQuery()->create($data)->toArray();
    }

    /** @param array<string, mixed> $data */
    public function update(int|string $id, array $data): bool
    {
        return $this->query()->where($this->model->getQualifiedKeyName(), $id)->update($data) > 0;
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
        $instance = $this->query()->where($this->model->getQualifiedKeyName(), $id)->first();

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
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
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
     * 解析 'sort asc, id desc' 形式的排序串：列必须在 $sortable 白名单内，方向不是 asc/desc 时按 asc；
     * 每段最多两个词（'id desc nulls' 之类视为格式错误）。列名带表名前缀。
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
            $parts = preg_split('/\s+/', $segment) ?: [];
            if (count($parts) > 2) {
                throw new \InvalidArgumentException("排序片段格式错误：{$segment}");
            }
            [$column, $direction] = array_pad($parts, 2, 'asc');
            if (!in_array($column, $this->sortable, true)) {
                throw new \InvalidArgumentException("不允许按 {$column} 排序");
            }
            $query->orderBy($this->qualify((string) $column), strtolower((string) $direction) === 'desc' ? 'desc' : 'asc');
        }

        return $query;
    }

    /** 表名限定的列名，如 qualify('id') → 'admins.id'。 */
    protected function qualify(string $column): string
    {
        return $this->model->qualifyColumn($column);
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
