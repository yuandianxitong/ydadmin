<?php
// 代码生成器模板：由 core\generator\TemplateRenderer 渲染（ob_start + require + extract）。
// 可用变量只有底稿 §3.1 那一套；$searchColumns 已由蓝图按 searchable 筛好，模板不再过滤。
// 排版提示：闭合标签后紧跟的换行会被 PHP 吃掉——开标签那一行之后要空两行，才是「开标签 + 一个空行」。
$studly = static fn (string $name): string => str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
$sortable = [$primaryKey];
foreach (['sort', 'created_at', 'updated_at'] as $candidate) {
    if ($table->has($candidate)) {
        $sortable[] = $candidate;
    }
}
$defaultOrder = $table->has('sort') ? "sort asc, {$primaryKey} desc" : "{$primaryKey} desc";
$labels = ['like' => '模糊', 'equal' => '精确', 'range' => '区间'];
$strategies = [];
$summary = [];
$needsLike = false;
foreach ($searchColumns as $column) {
    $strategy = $inference->queryStrategy($column);
    $strategies[$column->name] = $strategy;
    $summary[] = $column->name . '（' . ($labels[$strategy] ?? '精确') . '）';
    $needsLike = $needsLike || $strategy === 'like';
}
$searchText = $summary === [] ? '无' : implode('、', $summary);
$displayName = $tableComment !== '' ? $tableComment : $tableName;
// 查重方法体的缩进：受控表多包一层 DataScope::bypass() 闭包，比不受控时多四格。
$indent = $dataScoped ? '            ' : '        ';
?>
<?= '<?php' ?>


declare(strict_types=1);

namespace app\repository\<?= $module ?>;

use app\model\<?= $module ?>\<?= $model ?>;
use core\base\Model;
use core\base\Repository;
<?php if ($dataScoped && $uniqueColumns !== []): ?>
use core\datascope\DataScope;
<?php endif; ?>
<?php if ($needsLike): ?>
use core\support\Like;
<?php endif; ?>
<?php if ($softDeletes && $uniqueColumns !== []): ?>
use Illuminate\Database\Eloquent\SoftDeletingScope;
<?php endif; ?>

/**
 * <?= $displayName ?>仓储（<?= $tableName ?> 表）。由代码生成器生成，可直接手改。
 *
<?php if ($dataScoped): ?>
 * 受数据权限约束：表里有 <?= $creatorColumn ?? 'created_by' ?><?php if ($deptColumn !== null): ?> 与 <?= $deptColumn ?><?php endif; ?>，按 spec §5.2 自动接入；不想受控就把 $dataScoped 改成 false。
<?php elseif ($deptColumn !== null): ?>
 * 不受数据权限约束：表只有 <?= $deptColumn ?>、没有 created_by。core\base\Repository 的 $ownerColumn
 * 默认非空（'created_by'），DataScopeScope 在“仅本人”快照下无条件按它 orWhere；仅有部门列撑不住
 * 这个语义，判成受控会对一个不存在的列拼 SQL、直接报错。要支持「仅部门」的数据权限，先改基类
 * 语义，再把下面的 $dataScoped 手改成 true 并显式设置 $deptColumn。
<?php else: ?>
 * 不受数据权限约束：表里既没有 created_by 也没有 dept_id——照 FileRepository 的先例显式写出来，免得后来的人以为漏配。
<?php endif; ?>
 */
class <?= $model ?>Repository extends Repository
{
    /** @var list<string> */
    protected array $sortable = [<?= implode(', ', array_map(static fn (string $name): string => "'{$name}'", $sortable)) ?>];

    protected bool $dataScoped = <?= $dataScoped ? 'true' : 'false' ?>;

    protected ?string $creatorColumn = <?= $creatorColumn === null ? 'null' : "'{$creatorColumn}'" ?>;
<?php if ($deptColumn !== null): ?>

    protected ?string $deptColumn = '<?= $deptColumn ?>';
<?php endif; ?>

    protected function getModel(): Model
    {
        return new <?= $model ?>();
    }

    /**
     * 列表查询，搜索项：<?= $searchText ?>。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function get<?= $model ?>List(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

<?php foreach ($searchColumns as $column): ?>
<?php $strategy = $strategies[$column->name]; $var = '$search' . $studly($column->name); ?>
<?php if ($strategy === 'like'): ?>
        <?= $var ?> = trim((string) ($params['<?= $column->name ?>'] ?? ''));
        if (<?= $var ?> !== '') {
            $query->where($this->qualify('<?= $column->name ?>'), 'like', Like::contains(<?= $var ?>));
        }

<?php elseif ($strategy === 'range'): ?>
        <?= $var ?>Start = trim((string) ($params['<?= $column->name ?>_start'] ?? ''));
        if (<?= $var ?>Start !== '') {
            $query->where($this->qualify('<?= $column->name ?>'), '>=', <?= $var ?>Start);
        }

        <?= $var ?>End = trim((string) ($params['<?= $column->name ?>_end'] ?? ''));
        if (<?= $var ?>End !== '') {
            $query->where($this->qualify('<?= $column->name ?>'), '<=', <?= $var ?>End);
        }

<?php elseif ($column->type === 'integer'): ?>
        if (isset($params['<?= $column->name ?>']) && $params['<?= $column->name ?>'] !== '') {
            $query->where($this->qualify('<?= $column->name ?>'), (int) $params['<?= $column->name ?>']);
        }

<?php else: ?>
        <?= $var ?> = trim((string) ($params['<?= $column->name ?>'] ?? ''));
        if (<?= $var ?> !== '') {
            $query->where($this->qualify('<?= $column->name ?>'), <?= $var ?>);
        }

<?php endif; ?>
<?php endforeach; ?>
        $total = (clone $query)->count();
        $list = $this->applyOrder($query, '<?= $defaultOrder ?>')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }
<?php foreach ($uniqueColumns as $uniqueColumn): ?>

    /**
     * <?= $uniqueColumn ?> 是否已被占用（表上有唯一索引，Service 层写入前调它查重）。
     *
<?php if ($dataScoped): ?>
     * 经 DataScope::bypass() 看全表：查重必须看见数据范围外的行，否则受限管理员插得进重复值，再被唯一索引打成 500。
<?php endif; ?>
<?php if ($softDeletes): ?>
     * 含软删行：唯一索引对软删行同样生效。
<?php endif; ?>
     */
    public function existsBy<?= $studly($uniqueColumn) ?>(string $value, ?int $excludeId = null): bool
    {
<?php if ($dataScoped): ?>
        return DataScope::bypass(function () use ($value, $excludeId): bool {
<?php endif; ?>
<?= $indent ?>$query = $this->query()<?php if ($softDeletes): ?>->withoutGlobalScope(SoftDeletingScope::class)<?php endif; ?>->where($this->qualify('<?= $uniqueColumn ?>'), $value);
<?= $indent ?>if ($excludeId !== null) {
<?= $indent ?>    $query->where($this->qualify('<?= $primaryKey ?>'), '<>', $excludeId);
<?= $indent ?>}

<?= $indent ?>return $query->exists();
<?php if ($dataScoped): ?>
        });
<?php endif; ?>
    }
<?php endforeach; ?>
}
