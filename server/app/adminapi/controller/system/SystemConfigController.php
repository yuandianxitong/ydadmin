<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\service\system\SystemConfigService;
use core\base\Controller;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;

/** 系统配置。M1a 只有前端启动用的 global；分组、按组读写与清缓存随 M1b。 */
class SystemConfigController extends Controller
{
    #[Inject]
    protected SystemConfigService $systemConfigService;

    #[PermissionSkip]
    public function global(): Response
    {
        return $this->success($this->systemConfigService->getGlobalConfigs(), lang('messages.global_config_success'));
    }
}
