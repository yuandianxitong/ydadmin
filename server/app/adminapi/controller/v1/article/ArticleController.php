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
use app\service\article\ArticleService;
use app\adminapi\validate\v1\article\ArticleValidate;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '文章管理', description: '文章的增删改查、状态管理')]
class ArticleController extends Controller
{
    protected ArticleService $articleService;

    /**
     * 文章列表
     */
    #[Permission('article.list')]
    #[OA\Get(
        path: '/article/list',
        summary: '获取文章列表',
        security: [['bearerAuth' => []]],
        tags: ['文章管理'],
        parameters: [
            new OA\Parameter(name: 'page_no', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'page_size', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'keyword', in: 'query', description: '关键词搜索', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category_id', in: 'query', description: '分类ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', description: '状态(0禁用 1启用)', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function list(): Response
    {
        $params = $this->getRequestData([
            'page_no'     => 1,
            'page_size'   => 20,
            'keyword'     => '',
            'category_id' => '',
            'status'      => '',
        ]);
        $result = $this->articleService->getArticleList($params);
        return $this->paginate($result);
    }

    /**
     * 文章详情
     */
    #[Permission('article.list')]
    #[OA\Get(
        path: '/article/detail/{id}',
        summary: '获取文章详情',
        security: [['bearerAuth' => []]],
        tags: ['文章管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '文章ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function detail(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->articleService->getArticleDetail($id);
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 创建文章
     */
    #[Permission('article.create')]
    #[OA\Post(
        path: '/article',
        summary: '创建文章',
        security: [['bearerAuth' => []]],
        tags: ['文章管理'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'category_id', 'content'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', description: '文章标题'),
                    new OA\Property(property: 'category_id', type: 'integer', description: '分类ID'),
                    new OA\Property(property: 'cover', type: 'string', description: '封面图片'),
                    new OA\Property(property: 'summary', type: 'string', description: '摘要'),
                    new OA\Property(property: 'content', type: 'string', description: '文章内容'),
                    new OA\Property(property: 'tags', type: 'string', description: '标签'),
                    new OA\Property(property: 'author', type: 'string', description: '作者'),
                    new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
                    new OA\Property(property: 'publish_at', type: 'string', description: '发布时间'),
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
        $data = $this->request->only([
            'title', 'category_id', 'cover', 'summary', 'content',
            'tags', 'author', 'status', 'publish_at',
        ]);
        $this->validate($data, ArticleValidate::class, [], false, 'create');
        $data['admin_id'] = $this->getUserId();
        $result = $this->articleService->createArticle($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    /**
     * 更新文章
     */
    #[Permission('article.update')]
    #[OA\Put(
        path: '/article/{id}',
        summary: '更新文章',
        security: [['bearerAuth' => []]],
        tags: ['文章管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '文章ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', description: '文章标题'),
                    new OA\Property(property: 'category_id', type: 'integer', description: '分类ID'),
                    new OA\Property(property: 'cover', type: 'string', description: '封面图片'),
                    new OA\Property(property: 'summary', type: 'string', description: '摘要'),
                    new OA\Property(property: 'content', type: 'string', description: '文章内容'),
                    new OA\Property(property: 'tags', type: 'string', description: '标签'),
                    new OA\Property(property: 'author', type: 'string', description: '作者'),
                    new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
                    new OA\Property(property: 'publish_at', type: 'string', description: '发布时间'),
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
        $data = $this->request->only([
            'title', 'category_id', 'cover', 'summary', 'content',
            'tags', 'author', 'status', 'publish_at',
        ]);
        $this->validate($data, ArticleValidate::class, [], false, 'update');
        $this->articleService->updateArticle($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    /**
     * 删除文章
     */
    #[Permission('article.delete')]
    #[OA\Delete(
        path: '/article/{id}',
        summary: '删除文章',
        security: [['bearerAuth' => []]],
        tags: ['文章管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '文章ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->articleService->deleteArticle($id);
        return $this->success(lang('messages.delete_success'));
    }

    /**
     * 更新文章状态
     */
    #[Permission('article.status')]
    #[OA\Put(
        path: '/article/{id}/status',
        summary: '更新文章状态',
        security: [['bearerAuth' => []]],
        tags: ['文章管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '文章ID', schema: new OA\Schema(type: 'integer'))
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
        $this->articleService->updateStatus($id, $status);
        return $this->success(lang('messages.operation_success'));
    }
}
