<?php

declare(strict_types=1);

namespace app\adminapi\controller;

use app\service\operations\MonitoringService;
use core\base\Controller;
use core\response\Api;
use DI\Attribute\Inject;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

#[RouteGroup('/adminapi')]
class HealthController extends Controller
{
    #[Inject]
    protected MonitoringService $monitoring;

    #[Get('/health')]
    public function index(Request $request): Response
    {
        return $this->success([
            'status'  => 'ok',
            'version' => (string) config('version.version'),
        ]);
    }

    #[Get('/health/ready')]
    public function ready(): Response
    {
        $result = $this->monitoring->readiness();

        return $result['ready']
            ? $this->success($result)
            : Api::errorWithStatus(lang('operations.not_ready'), 503, $result);
    }
}
