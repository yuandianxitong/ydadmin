<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\plugin;

use app\model\plugin\Plugin;
use core\base\Repository;
use think\Model;

class PluginRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Plugin();
    }

    /**
     * 获取所有已安装插件
     */
    public function getInstalledPlugins(): array
    {
        return $this->model->select()->toArray();
    }

    /**
     * 根据名称查找插件
     */
    public function findByName(string $name): ?array
    {
        $result = $this->model->where('name', $name)->find();
        return $result ? $result->toArray() : null;
    }

    /**
     * 记录安装
     */
    public function createInstallRecord(array $data): int
    {
        $result = $this->model->create($data);
        return (int) $result->id;
    }

    /**
     * 删除安装记录
     */
    public function deleteByName(string $name): bool
    {
        return $this->model->where('name', $name)->delete() > 0;
    }

    /**
     * 更新状态
     */
    public function updateStatus(string $name, int $status): bool
    {
        return $this->model->where('name', $name)->update(['status' => $status]) > 0;
    }
}
