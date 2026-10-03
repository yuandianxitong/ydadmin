<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\AuthenticatedController;
use app\service\system\OnlineAdminService;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/**
 * 在线管理员（spec §6）。
 *
 * 端点：
 *   GET  /adminapi/system/online                        index   system.online.list
 *   POST /adminapi/system/online/{adminId:\d+}/logout    logout  system.online.logout
 *
 * 两个动作都不调 validate()：index 只用 pageParams()，logout 的 id 由路由正则约束，规则七不适用。
 */
#[RouteGroup('/adminapi/system/online')]
class OnlineController extends AuthenticatedController
{
    #[Inject]
    protected OnlineAdminService $onlineAdminService;

    #[Get('')]
    #[Permission('system.online.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->onlineAdminService->getList($page, $limit));
    }

    #[Post('/{adminId:\d+}/logout')]
    #[Permission('system.online.logout')]
    public function logout(Request $request, string $adminId): Response
    {
        return $this->success(['kicked' => $this->onlineAdminService->logout((int) $adminId)], lang('messages.success'));
    }
}
