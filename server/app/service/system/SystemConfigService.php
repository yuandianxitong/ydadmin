<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\SystemConfigRepository;
use core\base\Service;
use DI\Attribute\Inject;

class SystemConfigService extends Service
{
    /**
     * 凭据类键名片段（不区分大小写）：命中任一即不出现在 config/global（spec §6.1）。
     * 在 spec 列出的片段之外补了 pass、api_key/api_v3_key、aes_key，覆盖 smtp_pass、支付与微信的密钥。
     */
    private const SENSITIVE_KEY_PATTERN = '/secret|password|pass|access_key|private|token|api_(v\d+_)?key|aes_key/i';

    #[Inject]
    protected SystemConfigRepository $systemConfigRepository;

    /** 读启用配置的值（已按 config_type 转换，走缓存）。 */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->systemConfigRepository->getConfigValue($key, $default);
    }

    /**
     * 前端启动配置（契约 §4.1）：全部启用配置的扁平映射（已按 config_type 转换），排除凭据类键。
     *
     * @return array<string, mixed>
     */
    public function getGlobalConfigs(): array
    {
        return array_filter(
            $this->systemConfigRepository->getAllConfigs(),
            static fn (string $key): bool => !self::isSensitiveKey($key),
            ARRAY_FILTER_USE_KEY
        );
    }

    public static function isSensitiveKey(string $key): bool
    {
        return preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1;
    }
}
