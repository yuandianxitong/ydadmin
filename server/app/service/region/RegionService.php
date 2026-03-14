<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\region;

use app\repository\region\RegionRepository;
use core\base\Service;
use core\exception\BusinessException;

class RegionService extends Service
{
    protected RegionRepository $regionRepository;

    /**
     * 搜索地区列表（管理端）
     */
    public function getList(array $params): array
    {
        $page = (int) ($params['page_no'] ?? 1);
        $limit = (int) ($params['page_size'] ?? 20);
        return $this->regionRepository->getSearchList($params, $page, $limit);
    }

    /**
     * 获取地区树（级联选择器）
     */
    public function getTree(): array
    {
        return $this->regionRepository->getTree();
    }

    /**
     * 根据父级ID获取子级列表
     */
    public function getByParentId(int $parentId): array
    {
        return $this->regionRepository->getByParentId($parentId);
    }

    /**
     * 地区详情
     */
    public function detail(int $id): ?array
    {
        return $this->regionRepository->find($id);
    }

    /**
     * 创建地区
     */
    public function create(array $data): array
    {
        return $this->regionRepository->create($data);
    }

    /**
     * 更新地区
     */
    public function update(int $id, array $data): bool
    {
        return $this->regionRepository->update($id, $data);
    }

    /**
     * 删除地区
     */
    public function delete(int $id): bool
    {
        // 检查是否有子级
        $children = $this->regionRepository->getByParentId($id);
        if (!empty($children)) {
            throw new BusinessException('该地区下存在子级数据，无法删除');
        }

        return $this->regionRepository->delete($id);
    }
}
