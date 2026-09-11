<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\service\system\AdminService;
use app\service\system\MenuService;
use core\base\Controller;
use core\context\RequestContext;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;

class MenuController extends Controller
{
    #[Inject]
    protected MenuService $menuService;

    #[Inject]
    protected AdminService $adminService;

    /** 当前管理员的前端路由树，与 auth/info.routes 同源（契约 §4.3）。 */
    #[PermissionSkip]
    public function routes(): Response
    {
        $admin = $this->adminService->getSelfInfo(RequestContext::actingUser());

        return $this->success($this->menuService->getFrontendRoutes((array) $admin['menu_ids']), lang('messages.get_success'));
    }
}
