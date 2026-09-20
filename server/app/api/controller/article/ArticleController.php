<?php

declare(strict_types=1);

namespace app\api\controller\article;

use app\service\article\ArticleService;
use core\base\Controller;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * C 端文章只读（M7a spec §5.2）。公开，不挂 ApiAuthMiddleware。
 *
 *   GET /api/article/list           list    分页（page_no/page_size，默认 10），只含已发布
 *   GET /api/article/detail/{id}    detail  已发布详情；草稿 / 不存在 → body.code 404
 *
 * 权限点体系是管理端的，C 端方法一律 #[PermissionSkip]。
 */
class ArticleController extends Controller
{
    #[Inject]
    protected ArticleService $articleService;

    #[PermissionSkip]
    public function list(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request, 10);

        return $this->paginate($this->articleService->getPublishedList((array) $request->get(), $page, $limit));
    }

    #[PermissionSkip]
    public function detail(Request $request, string $id): Response
    {
        return $this->success($this->articleService->getPublishedDetail((int) $id), lang('messages.get_success'));
    }
}
