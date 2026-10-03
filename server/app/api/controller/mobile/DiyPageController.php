<?php

declare(strict_types=1);

namespace app\api\controller\mobile;

use app\service\diy\DiyPageService;
use core\base\Controller;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/**
 * C 端装修已发布页。公开，不挂 ApiAuthMiddleware。
 *
 *   GET /api/mobile/diy-page    show    key；缺 key → body.code 400；未发布 / 不存在 → 404
 *
 * 权限点体系是管理端的，C 端方法一律 #[PermissionSkip]。
 */
#[RouteGroup('/api/mobile')]
class DiyPageController extends Controller
{
    #[Inject]
    protected DiyPageService $diyPageService;

    #[Get('/diy-page')]
    #[PermissionSkip]
    public function show(Request $request): Response
    {
        $key = trim((string) $request->get('key', ''));
        if ($key === '') {
            throw new BusinessException(lang('diy.page_key_required'), 400);
        }
        $page = $this->diyPageService->getPublished($key);
        if ($page === null) {
            throw new NotFoundException();
        }

        return $this->success($page);
    }
}
