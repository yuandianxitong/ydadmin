<?php

declare(strict_types=1);

namespace app\adminapi\controller\operations;

use app\adminapi\controller\AuthenticatedController;
use app\service\operations\MonitoringService;
use core\auth\PermissionSkip;
use DI\Attribute\Inject;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;

#[RouteGroup('/adminapi/system/monitoring')]
final class MonitoringController extends AuthenticatedController
{
    #[Inject]
    protected MonitoringService $monitoring;

    #[Get('')]
    #[PermissionSkip]
    public function index(): Response
    {
        return $this->success($this->monitoring->snapshot());
    }
}
