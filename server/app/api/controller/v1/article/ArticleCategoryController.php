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
use app\service\article\ArticleCategoryService;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '文章分类', description: '文章分类列表')]
class ArticleCategoryController extends Controller
{
    protected ArticleCategoryService $articleCategoryService;

    #[OA\Get(
        path: '/article-category/list',
        summary: '获取文章分类列表（仅启用）',
        tags: ['文章分类'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function getList(): Response
    {
        $result = $this->articleCategoryService->getList(true);
        return $this->success(lang('messages.get_success'), $result);
    }
}
