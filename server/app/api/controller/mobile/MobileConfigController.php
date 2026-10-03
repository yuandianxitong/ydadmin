<?php

declare(strict_types=1);

namespace app\api\controller\mobile;

use app\service\mobile\MobileConfigService;
use core\base\Controller;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;

/**
 * C 端移动端配置。公开，不挂 ApiAuthMiddleware。
 *
 *   GET /api/mobile/config    show    getPublic()：get() + home_decoration
 *
 * 权限点体系是管理端的，C 端方法一律 #[PermissionSkip]。
 */
#[RouteGroup('/api/mobile')]
class MobileConfigController extends Controller
{
    #[Inject]
    protected MobileConfigService $mobileConfigService;

    #[Get('/config')]
    #[PermissionSkip]
    public function show(): Response
    {
        return $this->success($this->mobileConfigService->getPublic());
    }
}
