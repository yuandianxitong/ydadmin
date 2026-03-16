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
use OpenApi\Attributes as OA;

#[OA\Tag(name: '文章栏目', description: '文章分类的增删改查、状态管理')]
class ArticleCategoryController extends Controller
{
    protected ArticleCategoryService $articleCategoryService;

    /**
     * 分类列表（树形）
     */
    #[Permission('article_category.list')]
    #[OA\Get(
        path: '/article-category/list',
        summary: '获取文章分类列表（树形）',
        security: [['bearerAuth' => []]],
        tags: ['文章栏目'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function list(): Response
    {
        $result = $this->articleCategoryService->getList();
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 分类选项（下拉树形，跳过权限）
     */
    #[PermissionSkip]
    #[OA\Get(
        path: '/article-category/options',
        summary: '获取文章分类选项（下拉树形）',
        security: [['bearerAuth' => []]],
        tags: ['文章栏目'],
        parameters: [
            new OA\Parameter(name: 'exclude_id', in: 'query', description: '排除的分类ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
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
    #[OA\Post(
        path: '/article-category',
        summary: '创建文章分类',
        security: [['bearerAuth' => []]],
        tags: ['文章栏目'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'parent_id', type: 'integer', description: '父级分类ID'),
                    new OA\Property(property: 'name', type: 'string', description: '分类名称'),
                    new OA\Property(property: 'icon', type: 'string', description: '图标'),
                    new OA\Property(property: 'sort', type: 'integer', description: '排序'),
                    new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '创建成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '验证失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
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
    #[OA\Put(
        path: '/article-category/{id}',
        summary: '更新文章分类',
        security: [['bearerAuth' => []]],
        tags: ['文章栏目'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '分类ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'parent_id', type: 'integer', description: '父级分类ID'),
                    new OA\Property(property: 'name', type: 'string', description: '分类名称'),
                    new OA\Property(property: 'icon', type: 'string', description: '图标'),
                    new OA\Property(property: 'sort', type: 'integer', description: '排序'),
                    new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
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
    #[OA\Delete(
        path: '/article-category/{id}',
        summary: '删除文章分类',
        security: [['bearerAuth' => []]],
        tags: ['文章栏目'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '分类ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
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
    #[OA\Put(
        path: '/article-category/{id}/status',
        summary: '更新文章分类状态',
        security: [['bearerAuth' => []]],
        tags: ['文章栏目'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '分类ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '状态更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function updateStatus(): Response
    {
        $id = (int) $this->request->param('id');
        $status = (int) $this->request->post('status');
        $this->articleCategoryService->updateStatus($id, $status);
        return $this->success(lang('messages.operation_success'));
    }
}
