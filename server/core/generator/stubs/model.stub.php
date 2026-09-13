<?php
// 代码生成器模板：由 core\generator\TemplateRenderer 渲染（ob_start + require + extract）。
// 可用变量只有底稿 §3.1 那一套，$formColumns / $listColumns / $searchColumns 已由蓝图筛好，模板不再过滤。
// 排版提示：闭合标签后紧跟的换行会被 PHP 吃掉——开标签那一行之后要空两行，才是「开标签 + 一个空行」。
$casts = [];
foreach ($columns as $column) {
    $cast = $inference->cast($column);
    if ($cast !== null) {
        $casts[$column->name] = $cast;
    }
}
$castWidth = 0;
foreach (array_keys($casts) as $castName) {
    $castWidth = max($castWidth, strlen($castName) + 2);
}
$displayName = $tableComment !== '' ? $tableComment : $tableName;
?>
<?= '<?php' ?>


declare(strict_types=1);

namespace app\model\<?= $module ?>;

use core\base\Model;
<?php if ($softDeletes): ?>
use Illuminate\Database\Eloquent\SoftDeletes;
<?php endif; ?>

/** <?= $displayName ?>（<?= $tableName ?> 表）。由代码生成器生成，可直接手改。 */
class <?= $model ?> extends Model
{
<?php if ($softDeletes): ?>
    use SoftDeletes;

<?php endif; ?>
    protected $table = '<?= $tableName ?>';
<?php if ($primaryKey !== 'id'): ?>

    protected $primaryKey = '<?= $primaryKey ?>';
<?php endif; ?>
<?php if ($casts !== []): ?>

    /** @var array<string, string> */
    protected $casts = [
<?php foreach ($casts as $castName => $cast): ?>
        <?= str_pad("'{$castName}'", $castWidth) ?> => '<?= $cast ?>',
<?php endforeach; ?>
    ];
<?php endif; ?>
<?php if ($hasStatus): ?>

    /** @var list<string> */
    protected $appends = ['status_text'];

    /** status 的可读文案：1 正常，其余禁用（与 Dictionary 等手写模块一致）。 */
    public function getStatusTextAttribute(): string
    {
        if (!array_key_exists('status', $this->attributes)) {
            return '';
        }

        return (int) $this->attributes['status'] === 1 ? '正常' : '禁用';
    }
<?php endif; ?>
}
