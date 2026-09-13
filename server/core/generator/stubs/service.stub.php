<?php
// service.stub.php —— 渲染产物 key: service → app/service/{module}/{Model}Service.php。
//
// 可用模板变量只有底稿 §3.1 那一套；$formColumns 已由 ModuleBlueprint 按 in_form 开关筛好，
// 模板里不得再过滤——筛选口径只能有一处。
//
// 生成的代码必须满足（CLAUDE.md + spec §6.1）：
//   1. 只调 Repository，正文里不出现 Db::（check:context 规则二扫 app/service）；
//   2. 写操作一律包 runInTransaction()，缓存失效等副作用用 afterCommit()；
//   3. update 取白名单交集 array_filter(array_intersect_key(...), fn ($v) => $v !== null)；
//   4. 唯一列不做成校验规则（spec 决策 12），改为「Repository::existsBy{Column}() 预检查 + 唯一索引异常兜底」。
//      预检查刻意走 Repository 那个方法而不是基类 exists()：查重必须看得见数据范围外的行与软删行
//      （唯一索引对两者同样生效），否则受限管理员只会撞到索引、拿不到有用的业务提示。
//
// 生成物里要输出 PHP 开标签时，一律用短输出标签回显字符串，不要在模板里直接写标签本身。
$repository = lcfirst($model) . 'Repository';
// 与 repository.stub.php 产出的 existsBy{Studly} 同一套命名——两边不一致 Service 就调不到那个方法
$studly = static fn (string $name): string => str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
// 预检查的真实语义，逐条取自 repository.stub.php 里 existsBy* 实际生成的代码，不是想当然的描述
$precheckScope = [];
if ($dataScoped) {
    $precheckScope[] = '经 DataScope::bypass() 看全表';
}
if ($softDeletes) {
    $precheckScope[] = '含软删行';
}
$precheckNote = $precheckScope === [] ? '' : '（' . implode('、', $precheckScope) . '）';
$writable = implode(', ', array_map(
    static fn (\core\generator\ColumnDescriptor $column): string => "'" . $column->name . "'",
    $formColumns
));
// 兜底用的重复键：只有一个唯一列时就用它自己的键，多个唯一列时异常里分不出是哪一列，用统一的键
$duplicateKey = count($uniqueColumns) === 1
    ? $module . '.' . $modelSnake . '_' . $uniqueColumns[0] . '_exists'
    : $module . '.' . $modelSnake . '_duplicate';
?>
<?= '<?php' ?>


declare(strict_types=1);

namespace app\service\<?= $module ?>;

use app\repository\<?= $module ?>\<?= $model ?>Repository;
use core\base\Service;
use core\exception\BusinessException;
use DI\Attribute\Inject;
<?php if ($uniqueColumns !== []) { ?>
use Illuminate\Database\UniqueConstraintViolationException;
<?php } ?>

/**
 * <?= $tableCommentPhpDoc ?>（由代码生成器生成）。
 *
 * 只调 Repository：查询条件与数据权限都在 <?= $model ?>Repository 里，这一层不直接调用数据库门面。
 * 写操作一律包 runInTransaction()；缓存失效之类的副作用请在事务里用 afterCommit() 追加。
<?php if ($uniqueColumns !== []) { ?>
 *
 * 唯一性（<?= implode('、', $uniqueColumns) ?>）：写入前调 <?= $model ?>Repository 的 existsBy* 预检查<?= $precheckNote ?>，
 * 命中就抛业务提示；更新时把自己这一行用 $excludeId 排除掉。并发写入撞上唯一索引时捕获
 * UniqueConstraintViolationException 转成同一条业务错误，不返回 500。
<?php } ?>
 */
class <?= $model ?>Service extends Service
{
    #[Inject]
    protected <?= $model ?>Repository $<?= $repository ?>;

    /**
     * 列表：keyword、区间等查询条件在 Repository 里按列类型展开。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function get<?= $model ?>List(array $params, int $page, int $limit): array
    {
        return $this-><?= $repository ?>->get<?= $model ?>List($params, $page, $limit);
    }

    /** @return array<string, mixed> */
    public function get<?= $model ?>Detail(int $id): array
    {
        return $this->find<?= $model ?>OrFail($id);
    }

    /**
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     * @return array<string, mixed>
     */
    public function create<?= $model ?>(array $data): array
    {
        $row = array_intersect_key($data, array_flip([<?= $writable ?>]));
<?php foreach ($uniqueColumns as $unique) { ?>
        if (isset($row['<?= $unique ?>']) && $this-><?= $repository ?>->existsBy<?= $studly($unique) ?>((string) $row['<?= $unique ?>'])) {
            throw new BusinessException(lang('<?= $module ?>.<?= $modelSnake ?>_<?= $unique ?>_exists'));
        }
<?php } ?>
<?php if ($uniqueColumns === []) { ?>

        return $this->runInTransaction(fn (): array => $this-><?= $repository ?>->create($row));
<?php } else { ?>

        try {
            return $this->runInTransaction(fn (): array => $this-><?= $repository ?>->create($row));
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException(lang('<?= $duplicateKey ?>'));
        }
<?php } ?>
    }

    /**
     * 局部更新：只写传了的字段，值为 null 的丢弃；白名单与 create 一致。
     *
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     */
    public function update<?= $model ?>(int $id, array $data): void
    {
        $this->find<?= $model ?>OrFail($id);
        $update = array_filter(
            array_intersect_key($data, array_flip([<?= $writable ?>])),
            static fn ($value) => $value !== null
        );
        if ($update === []) {
            return;
        }
<?php foreach ($uniqueColumns as $unique) { ?>
        // 自己这一行由 $excludeId 排除，不必先比较值有没有改动：那次字符串比较依赖 find() 的返回值，
        // 而 find() 受数据权限约束，取不到该列时 ?? '' 会把「没改」误判成「改了」，白跑一次查重。
        if (isset($update['<?= $unique ?>']) && $this-><?= $repository ?>->existsBy<?= $studly($unique) ?>((string) $update['<?= $unique ?>'], $id)) {
            throw new BusinessException(lang('<?= $module ?>.<?= $modelSnake ?>_<?= $unique ?>_exists'));
        }
<?php } ?>
<?php if ($uniqueColumns === []) { ?>

        $this->runInTransaction(function () use ($id, $update): void {
            $this-><?= $repository ?>->update($id, $update);
        });
<?php } else { ?>

        try {
            $this->runInTransaction(function () use ($id, $update): void {
                $this-><?= $repository ?>->update($id, $update);
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException(lang('<?= $duplicateKey ?>'));
        }
<?php } ?>
    }

    public function delete<?= $model ?>(int $id): void
    {
        $this->find<?= $model ?>OrFail($id);

        $this->runInTransaction(function () use ($id): void {
            $this-><?= $repository ?>->delete($id);
        });
    }

    /**
     * 批量删除：同一事务内逐条删除，任一 id 不存在则整体回滚（与 M1 的角色、字典批量删除同语义）。
     * 重复 id 先去重。
     *
     * @param list<int> $ids
     */
    public function batchDelete(array $ids): void
    {
        $this->runInTransaction(function () use ($ids): void {
            foreach (array_values(array_unique($ids)) as $id) {
                $this->delete<?= $model ?>($id);
            }
        });
    }
<?php if ($hasStatus) { ?>

    public function updateStatus(int $id, int $status): void
    {
        $this->find<?= $model ?>OrFail($id);

        $this->runInTransaction(function () use ($id, $status): void {
            $this-><?= $repository ?>->update($id, ['status' => $status]);
        });
    }
<?php } ?>

    /** @return array<string, mixed> */
    private function find<?= $model ?>OrFail(int $id): array
    {
        return $this-><?= $repository ?>->find($id) ?? throw new BusinessException(lang('<?= $module ?>.<?= $modelSnake ?>_not_found'));
    }
}
