<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\model;

use think\model\Pivot as BasePivot;

class Pivot extends BasePivot
{
    // 中间表通常都有时间戳
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 强制设置中间表没有自增字段
    protected $auto = null;

    // 确保中间表的自增字段处理正确 - 强制返回 null
    public function getAutoInc()
    {
        // 中间表使用复合主键，绝对没有自增字段
        return null;
    }

    // 重写 getPk 方法，确保框架正确识别复合主键
    public function getPk()
    {
        $pk = parent::getPk();
        // 如果是数组主键且不为空，返回字符串形式避免冲突
        if (is_array($pk) && !empty($pk)) {
            // 对于中间表，不应该有单独的主键被当作自增字段
            return $pk;
        }
        return $pk;
    }
}
