<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\article;

use core\base\Controller;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use app\service\article\ArticleCategoryService;
use app\adminapi\validate\v1\article\ArticleCategoryValidate;
use think\Response;

class ArticleCategoryController extends Controller
{
    protected ArticleCategoryService $articleCategoryService;

    /**
     * 分类列表（树形）
     */
    #[Permission('article_category.list')]
    public function list(): Response
    {
        $result = $this->articleCategoryService->getList();
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 分类选项（下拉树形，跳过权限）
     */
    #[PermissionSkip]
    public function options(): Response
    {
        $excludeId = (int) $this->request->param('exclude_id', 0);
        $result = $this->articleCategoryService->getOptions($excludeId);
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 创建分类
     */
    #[Permission('article_category.create')]
    public function create(): Response
    {
        $data = $this->request->only(['parent_id', 'name', 'icon', 'sort', 'status']);
        $this->validate($data, ArticleCategoryValidate::class, [], false, 'create');
        $result = $this->articleCategoryService->create($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    /**
     * 更新分类
     */
    #[Permission('article_category.update')]
    public function update(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->only(['parent_id', 'name', 'icon', 'sort', 'status']);
        $this->validate($data, ArticleCategoryValidate::class, [], false, 'update');
        $this->articleCategoryService->update($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    /**
     * 删除分类
     */
    #[Permission('article_category.delete')]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->articleCategoryService->delete($id);
        return $this->success(lang('messages.delete_success'));
    }

    /**
     * 更新分类状态
     */
    #[Permission('article_category.update')]
    public function updateStatus(): Response
    {
        $id = (int) $this->request->param('id');
        $status = (int) $this->request->post('status');
        $this->articleCategoryService->updateStatus($id, $status);
        return $this->success(lang('messages.operation_success'));
    }
}
