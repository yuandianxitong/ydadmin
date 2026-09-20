<?php

declare(strict_types=1);

namespace app\api\controller\announcement;

use app\service\announcement\AnnouncementService;
use core\base\Controller;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * C 端公告只读（M7a spec §5）。公开，不挂 ApiAuthMiddleware。
 *
 *   GET /api/announcement/list           list    分页（page_no/page_size，默认 10），只含已发布
 *   GET /api/announcement/detail/{id}    detail  已发布详情；草稿 / 不存在 → body.code 404
 *
 * 权限点体系是管理端的，C 端方法一律 #[PermissionSkip]。
 */
class AnnouncementController extends Controller
{
    #[Inject]
    protected AnnouncementService $announcementService;

    #[PermissionSkip]
    public function list(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request, 10);

        return $this->paginate($this->announcementService->getPublishedList((array) $request->get(), $page, $limit));
    }

    #[PermissionSkip]
    public function detail(Request $request, string $id): Response
    {
        return $this->success($this->announcementService->getPublishedDetail((int) $id), lang('messages.get_success'));
    }
}
