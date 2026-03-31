<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\article;

use app\model\article\ArticleCategory;
use core\base\Repository;
use think\Model;

class ArticleCategoryRepository extends Repository
{
    protected function getModel(): Model
    {
        return new ArticleCategory();
    }

    /**
     * 获取分类树形列表
     */
    public function getCategoryTree(bool $onlyEnabled = false): array
    {
        $query = $this->model->order('sort asc, id asc');

        if ($onlyEnabled) {
            $query->where('status', 1);
        }

        return $query->select()->toArray();
    }

    /**
     * 获取模型实例（用于更新操作）
     */
    public function findModel(int $id): ?ArticleCategory
    {
        return ArticleCategory::find($id);
    }

    /**
     * 检查名称是否已存在
     */
    public function existsName(string $name, int $excludeId = 0): bool
    {
        $query = $this->model->where('name', $name);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }

    /**
     * 检查是否有子分类
     */
    public function hasChildren(int $id): bool
    {
        return $this->model->where('parent_id', $id)->count() > 0;
    }

    /**
     * 检查分类下是否有文章
     */
    public function hasArticles(int $id): bool
    {
        return \think\facade\Db::table('articles')
            ->where('category_id', $id)
            ->where('deleted_at', null)
            ->count() > 0;
    }

    /**
     * 获取分类选项列表（用于下拉选择）
     */
    public function getOptions(int $excludeId = 0): array
    {
        $query = $this->model->where('status', 1)
            ->field('id, parent_id, name')
            ->order('sort asc, id asc');

        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->select()->toArray();
    }
}
