<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\SystemConfigRepository;
use core\base\Service;
use DI\Attribute\Inject;

class SystemConfigService extends Service
{
    #[Inject]
    protected SystemConfigRepository $systemConfigRepository;

    /** 读启用配置的值（已按 config_type 转换，走缓存）。 */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->systemConfigRepository->getConfigValue($key, $default);
    }
}
