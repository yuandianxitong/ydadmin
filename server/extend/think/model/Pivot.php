<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace think\model;

use think\Model;

/**
 * 多对多中间表模型类（PHP 8.4 兼容补丁）
 *
 * think-orm v4.0.40 的 Pivot 模型在复合主键场景下，
 * getAutoInc() 返回数组，但 Query::autoinc(?string) 要求字符串，
 * 在 PHP 8.4 的严格类型检查下会抛出 TypeError。
 *
 * 此文件通过 composer.json classmap 优先加载，覆盖 vendor 中的原始类。
 */
class Pivot extends Model
{
    /**
     * 父模型.
     *
     * @var Model
     */
    public $parent;
    protected $pivotName;

    /**
     * 是否时间自动写入.
     *
     * @var bool
     */
    protected $autoWriteTimestamp = false;

    /**
     * 架构函数.
     *
     * @param array      $data   数据
     * @param Model|null $parent 上级模型
     * @param string     $table  中间数据表名
     */
    public function __construct(array $data = [], ?Model $parent = null, string $table = '')
    {
        $this->pivotName   = $table;
        $this->parent      = $parent;
        parent::__construct($data);
    }

    /**
     *  初始化模型.
     *
     * @return void
     */
    protected function init()
    {
        if (is_null($this->getOption('name'))) {
            $this->setOption('name', $this->pivotName);
        }
    }

    /**
     * 创建新的模型实例.
     *
     * @param array|object $data    数据
     * @param array        $options
     *
     * @return Model
     */
    public function newInstance(array | object $data = [], array $options = [])
    {
        $this->data($data);
        return $this->clone();
    }

    /**
     * 获取自增主键（PHP 8.4 兼容修复）
     *
     * 中间表通常使用复合主键（如 admin_id + role_id），没有自增列。
     * 父类 getAutoInc() 在未设置 autoInc 选项时回退到 getPk()，
     * 对复合主键返回数组，导致 Query::autoinc(?string) 类型错误。
     *
     * @return string|null
     */
    public function getAutoInc()
    {
        $autoInc = parent::getAutoInc();
        return is_array($autoInc) ? null : $autoInc;
    }
}
