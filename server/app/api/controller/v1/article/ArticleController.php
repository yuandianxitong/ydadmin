<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\api\controller\v1\article;

use core\base\Controller;
use app\service\article\ArticleService;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '文章', description: '文章列表与详情')]
class ArticleController extends Controller
{
    protected ArticleService $articleService;

    #[OA\Get(
        path: '/article/list',
        summary: '获取文章列表（已发布）',
        tags: ['文章'],
        parameters: [
            new OA\Parameter(name: 'page_no', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'page_size', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 10)),
            new OA\Parameter(name: 'category_id', in: 'query', description: '分类ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse')),
        ]
    )]
    public function getList(): Response
    {
        $params = $this->getRequestData([
            'page_no'     => 1,
            'page_size'   => 10,
            'category_id' => '',
        ]);
        $result = $this->articleService->getPublishedList($params);
        return $this->paginate($result);
    }

    #[OA\Get(
        path: '/article/detail/{id}',
        summary: '获取文章详情',
        tags: ['文章'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '文章ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function getDetail(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->articleService->getArticleDetail($id);
        return $this->success(lang('messages.get_success'), $result);
    }
}
