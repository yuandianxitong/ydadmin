<?php

declare(strict_types=1);

namespace app\api\controller\version;

use app\service\version\AppVersionService;
use core\base\Controller;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * C 端版本检查。公开，不挂 ApiAuthMiddleware。
 *
 *   GET /api/version/check    check    platform + version_code；缺参 / version_code≤0 → body.code 400
 *
 * 权限点体系是管理端的，C 端方法一律 #[PermissionSkip]。
 */
class VersionController extends Controller
{
    #[Inject]
    protected AppVersionService $appVersionService;

    #[PermissionSkip]
    public function check(Request $request): Response
    {
        return $this->success($this->appVersionService->checkUpdate(
            (string) $request->get('platform', ''),
            (int) $request->get('version_code', 0)
        ));
    }
}
