<?php

declare(strict_types=1);

namespace app\api\controller\article;

use app\service\article\ArticleCategoryService;
use core\base\Controller;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;

/**
 * C 端文章栏目只读（M7a spec §5.2）。公开，不挂 ApiAuthMiddleware。
 *
 *   GET /api/article-category/list   list   仅启用栏目树
 *
 * 权限点体系是管理端的，C 端方法一律 #[PermissionSkip]。
 */
class ArticleCategoryController extends Controller
{
    #[Inject]
    protected ArticleCategoryService $articleCategoryService;

    #[PermissionSkip]
    public function list(): Response
    {
        return $this->success($this->articleCategoryService->getTree(['status' => 1]));
    }
}
