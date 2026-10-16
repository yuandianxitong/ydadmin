<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\AuthenticatedController;
use app\service\system\ModuleService;
use core\auth\Permission;
use DI\Attribute\Inject;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;

#[RouteGroup('/adminapi/system/modules')]
final class ModuleController extends AuthenticatedController
{
    #[Inject]
    protected ModuleService $modules;

    #[Get('')]
    #[Permission('system.generator.list')]
    public function index(): Response
    {
        return $this->success($this->modules->inspect());
    }
}
