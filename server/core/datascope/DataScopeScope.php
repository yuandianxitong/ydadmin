<?php

declare(strict_types=1);

namespace core\datascope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * 数据权限条件（spec §5.2）。Repository::query() 在受控表上把它按查询挂成 Eloquent 全局作用域。
 *
 * 为什么用全局作用域：Eloquent 执行前套用作用域（Builder::applyScopes()）时，先把调用方已写的条件
 * 整体包进一组括号，再追加作用域自己的条件。调用方即使写了顶层 orWhere，也只能在自己的括号里「或」，
 * 越不出数据权限。
 *
 * 快照在 query() 那一刻取定并随实例携带：查询建好后再进出 DataScope::bypass() 或切换身份，都不改变
 * 这条查询的范围。列名由 Repository 传入，已带表名前缀。只作用于根表：with() 预加载不再过滤。
 * 只读、无状态，每条查询 new 一个，不进容器。
 *
 * @implements Scope<Model>
 */
final readonly class DataScopeScope implements Scope
{
    public function __construct(
        private DataScopeSnapshot $snapshot,
        private string $ownerColumn,
        private ?string $deptColumn,
    ) {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $snapshot = $this->snapshot;
        $owner = $this->ownerColumn;
        $deptColumn = $this->deptColumn;
        $builder->where(static function (Builder $q) use ($snapshot, $owner, $deptColumn): void {
            if ($snapshot->deptIds !== []) {
                if ($deptColumn !== null) {
                    $q->whereIn($deptColumn, $snapshot->deptIds);
                } else {
                    $q->whereIn($owner, static fn ($sub) => $sub->select('id')->from('admins')->whereIn('department_id', $snapshot->deptIds));
                }
            }
            if ($snapshot->self) {
                $q->orWhere($owner, $snapshot->adminId);
            }
            if ($snapshot->deptIds === [] && !$snapshot->self) {
                $q->whereRaw('1 = 0');
            }
        });
    }
}
