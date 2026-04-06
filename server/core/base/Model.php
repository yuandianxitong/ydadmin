<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\base;

use think\Model as BaseModel;
use think\model\concern\SoftDelete;

abstract class Model extends BaseModel
{
    // 统一使用软删除
    use SoftDelete;

    protected $deleteTime = 'deleted_at';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $hidden = ['deleted_at'];

    /**
     * 兼容性处理：确保主键、自增键为字符串，适配 PHP 8 严格类型
     */
    public function getPk()
    {
        $pk = parent::getPk();
        return is_array($pk) ? ($pk[0] ?? 'id') : ($pk ?: 'id');
    }

    public function getAutoInc()
    {
        $auto = parent::getAutoInc();

        // 如果主键是复合主键（数组），则没有自增字段
        $pk = parent::getPk();
        if (is_array($pk) && count($pk) > 1) {
            return null;
        }

        if (is_array($auto)) {
            $auto = $auto[0] ?? null;
        }

        // 确保返回字符串或null，不返回数组
        if (is_array($auto)) {
            $auto = null;
        }

        return $auto ?: (is_array($this->getPk()) ? ($this->getPk()[0] ?? 'id') : ($this->getPk() ?: 'id'));
    }

    /**
     * 纠正 Query 上的自增键类型（防止数组被传入 autoinc)
     */
    public function getQuery()
    {
        $query = parent::getQuery();
        $auto = $this->getAutoInc();

        // 确保自增字段为字符串或null
        if (is_array($auto)) {
            $auto = null;
        }

        // 只有当自增字段不为空且为字符串时才设置
        if ($auto && is_string($auto)) {
            $query->autoinc($auto);
        }

        return $query;
    }

    /**
     * 获取格式化的创建时间
     */
    public function getCreatedAtTextAttr($value, $data): string
    {
        return date('Y-m-d H:i:s', strtotime($data['created_at']));
    }

    /**
     * 获取格式化的更新时间
     */
    public function getUpdatedAtTextAttr($value, $data): string
    {
        return date('Y-m-d H:i:s', strtotime($data['updated_at']));
    }

    /**
     * 获取时间戳
     */
    public function getCreatedAtTimestampAttr($value, $data): int
    {
        return strtotime($data['created_at']);
    }

    /**
     * 获取时间戳
     */
    public function getUpdatedAtTimestampAttr($value, $data): int
    {
        return strtotime($data['updated_at']);
    }

    /**
     * 状态文本获取器（辅助方法）
     *
     * @param int $status
     * @param array $statusMap 自定义状态映射
     */
    protected function getStatusText(int $status, array $statusMap): string
    {
        return $statusMap[$status] ?? '未知';
    }

    /**
     * 默认状态文本访问器
     *
     * 所有含 `status` 字段且使用通用启/禁用语义的 Model 可以直接继承此默认实现，
     * 只需要在子类声明 `protected $append = ['status_text']`。
     *
     * 若业务语义不同（如草稿/已发布/审核中），子类 override 此方法。
     */
    public function getStatusTextAttr($value, $data): string
    {
        if (!isset($data['status'])) {
            return '';
        }
        return $this->getStatusText((int) $data['status'], [1 => '启用', 0 => '禁用']);
    }

    /**
     * JSON字段获取器
     */
    protected function getJsonAttr($value): array
    {
        return json_decode($value ?: '[]', true);
    }

    /**
     * JSON字段修改器
     */
    protected function setJsonAttr($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
