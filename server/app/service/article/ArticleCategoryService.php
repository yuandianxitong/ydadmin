<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\article;

use app\repository\article\ArticleCategoryRepository;
use core\base\Service;

class ArticleCategoryService extends Service
{
    protected ArticleCategoryRepository $articleCategoryRepository;

    /**
     * 获取分类树形列表
     */
    public function getList(bool $onlyEnabled = false): array
    {
        $list = $this->articleCategoryRepository->getCategoryTree($onlyEnabled);
        return $this->buildTree($list);
    }

    /**
     * 获取分类选项列表（下拉）
     */
    public function getOptions(int $excludeId = 0): array
    {
        $list = $this->articleCategoryRepository->getOptions($excludeId);
        return $this->buildTree($list);
    }

    /**
     * 创建分类
     */
    public function create(array $data): array
    {
        if ($this->articleCategoryRepository->existsName($data['name'])) {
            $this->throwBusinessException(lang('business.name_already_exists'));
        }

        if (!empty($data['parent_id'])) {
            $parent = $this->articleCategoryRepository->find((int) $data['parent_id']);
            if (!$parent) {
                $this->throwBusinessException(lang('business.parent_not_found'));
            }
        }

        return $this->articleCategoryRepository->create($data);
    }

    /**
     * 更新分类
     */
    public function update(int $id, array $data): bool
    {
        $category = $this->articleCategoryRepository->find($id);
        if (!$category) {
            $this->throwBusinessException(lang('business.record_not_found'));
        }

        if (!empty($data['name']) && $this->articleCategoryRepository->existsName($data['name'], $id)) {
            $this->throwBusinessException(lang('business.name_already_exists'));
        }

        // 不能将自己设为上级
        if (!empty($data['parent_id']) && (int) $data['parent_id'] === $id) {
            $this->throwBusinessException(lang('business.parent_not_self'));
        }

        return $this->articleCategoryRepository->update($id, $data);
    }

    /**
     * 删除分类
     */
    public function delete(int $id): bool
    {
        $category = $this->articleCategoryRepository->find($id);
        if (!$category) {
            $this->throwBusinessException(lang('business.record_not_found'));
        }

        // 检查是否有子分类
        if ($this->articleCategoryRepository->hasChildren($id)) {
            $this->throwBusinessException(lang('business.category_has_children'));
        }

        // 检查是否有关联文章
        if ($this->articleCategoryRepository->hasArticles($id)) {
            $this->throwBusinessException(lang('business.category_has_articles'));
        }

        return $this->articleCategoryRepository->delete($id);
    }

    /**
     * 更新分类状态
     */
    public function updateStatus(int $id, int $status): bool
    {
        $category = $this->articleCategoryRepository->find($id);
        if (!$category) {
            $this->throwBusinessException(lang('business.record_not_found'));
        }

        return $this->articleCategoryRepository->update($id, ['status' => $status]);
    }

    /**
     * 构建树形结构
     */
    protected function buildTree(array $list, int $parentId = 0): array
    {
        $tree = [];
        foreach ($list as $item) {
            if ((int) $item['parent_id'] === $parentId) {
                $children = $this->buildTree($list, (int) $item['id']);
                if (!empty($children)) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }
}
