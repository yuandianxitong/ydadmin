<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\base;

use think\Model;
use think\Collection;
use core\exception\BusinessException;

abstract class Repository
{
    protected Model $model;

    public function __construct()
    {
        $this->model = $this->getModel();
    }

    /**
     * 获取模型实例
     */
    abstract protected function getModel(): Model;

    /**
     * 查找单个记录
     */
    public function find($id): ?array
    {
        $result = $this->model->find($id);
        return $result ? $result->toArray() : null;
    }

    /**
     * 根据条件查找单个记录
     */
    public function findWhere(array $where): ?array
    {
        $result = $this->model->where($where)->find();
        return $result ? $result->toArray() : null;
    }

    /**
     * 创建记录
     */
    public function create(array $data): array
    {
        try {
            $result = $this->model->create($data);
            return $result->toArray();
        } catch (\Exception $e) {
            throw new BusinessException(lang('messages.create_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 批量创建
     */
    public function createAll(array $data): Collection
    {
        try {
            return $this->model->saveAll($data);
        } catch (\Exception $e) {
            throw new BusinessException(lang('messages.batch_create_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 更新记录
     */
    public function update($id, array $data): bool
    {
        try {
            return $this->model->where('id', $id)->update($data) > 0;
        } catch (\Exception $e) {
            throw new BusinessException(lang('messages.update_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 根据条件更新
     */
    public function updateWhere(array $where, array $data): int
    {
        try {
            return $this->model->where($where)->update($data);
        } catch (\Exception $e) {
            throw new BusinessException(lang('messages.update_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 删除记录
     */
    public function delete($id): bool
    {
        try {
            return $this->model->where('id', $id)->delete() > 0;
        } catch (\Exception $e) {
            throw new BusinessException(lang('messages.delete_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 根据条件删除
     */
    public function deleteWhere(array $where): int
    {
        try {
            return $this->model->where($where)->delete();
        } catch (\Exception $e) {
            throw new BusinessException(lang('messages.delete_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 获取列表
     */
    public function getList(array $where = [], int $page = 1, int $limit = 15, string $order = 'id desc'): array
    {
        $query = $this->model->where($where);

        $total = $query->count();
        $list = $query->page($page, $limit)->order($order)->select()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 构造统一的分页响应结构
     *
     * 用于 Repository 的自定义 `getSearchList` 方法：当查询条件复杂无法直接复用 `getList` 时，
     * 各子类只需查询出 $list 和 $total，然后调用此方法返回统一结构，避免到处手写 `pagination` 数组。
     */
    protected function buildPagination(array $list, int $page, int $limit, int $total): array
    {
        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => $limit > 0 ? (int) ceil($total / $limit) : 1,
            ],
        ];
    }

    /**
     * 获取所有记录
     */
    public function getAll(array $where = [], string $order = 'id desc'): array
    {
        return $this->model->where($where)->order($order)->select()->toArray();
    }

    /**
     * 统计记录数
     */
    public function count(array $where = []): int
    {
        return $this->model->where($where)->count();
    }

    /**
     * 检查记录是否存在
     */
    public function exists(array $where): bool
    {
        return $this->model->where($where)->count() > 0;
    }

    /**
     * 获取字段值
     */
    public function value(array $where, string $field)
    {
        return $this->model->where($where)->value($field);
    }

    /**
     * 获取字段列表
     */
    public function column(array $where, string $field, string $key = ''): array
    {
        return $this->model->where($where)->column($field, $key);
    }

    /**
     * 字段自增
     */
    public function inc(array $where, string $field, int $step = 1): int
    {
        return $this->model->where($where)->inc($field, $step)->update();
    }

    /**
     * 字段自减
     */
    public function dec(array $where, string $field, int $step = 1): int
    {
        return $this->model->where($where)->dec($field, $step)->update();
    }
}
