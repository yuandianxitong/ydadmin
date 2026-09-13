<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\SystemConfigRepository;
use core\base\Service;
use core\exception\BusinessException;
use DI\Attribute\Inject;

/**
 * 系统配置（契约 §2.7）。
 *
 * - config/global 有两道闸：先只取 status=1 且 is_public=1 的行（白名单），再过一遍凭据类键名黑名单。
 *   凭据键即使被误标公开也出不去。spec §6.1 原文只有黑名单，这里细化为「白名单 + 黑名单」，
 *   由 M1a 最终评审提出，用户已认可。
 * - 单条与批量写配置都在事务里完成，提交后经 afterCommit 清配置缓存（全部、公开两份）。
 *   批量更新里出现不存在的键时整批回滚。
 * - clear-cache 只清配置缓存。权限、字典、验证码、token 黑名单与版本号都不动（spec §1.1 第 4 条，红线 Test14）。
 */
class SystemConfigService extends Service
{
    /**
     * 凭据类键名片段，不区分大小写。命中任一，即使 is_public=1 也不出现在 config/global（spec §6.1）。
     * 在 spec 列出的片段之外，补了 pass、api_key/api_v3_key、aes_key，以及以 _key 结尾的键（mch_key、app_key 等）。
     * 最后一条锚定结尾，所以 site_keywords 不受影响。
     */
    private const SENSITIVE_KEY_PATTERN = '/secret|password|pass|access_key|private|token|api_(v\d+_)?key|aes_key|_key$/i';

    /** 登录安全开关认定为「关」的取值（不区分大小写、忽略首尾空白）；其余非空值一律按「开」。 */
    private const SWITCH_OFF_VALUES = ['0', 'false', 'no', 'off'];

    /**
     * 这两项是「按 MB 数配置的上传大小上限」，最终都要与 Workerman 的 `max_package_size`
     * （config/server.php）比较（Task 7 ruling 1）：包体（含 multipart 头）超过这个值时，
     * Workerman 在应用代码跑之前就把连接断开，前端看到的是连接重置而不是本地化的错误文案。
     * 管理员能填的上限因此不能顶格等于 max_package_size，必须留出协议开销的余量。
     */
    private const UPLOAD_SIZE_CONFIG_KEYS = ['storage_upload_max_size', 'storage_image_max_size'];

    /** 协议开销余量：multipart 头、boundary 等；留得比实际开销宽裕得多，不是精确计算。 */
    private const UPLOAD_SIZE_PACKAGE_BUFFER_BYTES = 20 * 1024 * 1024;

    /**
     * 配置分组：契约 §2.7 硬编码的 5 组，是前端配置页 tab 的来源。形式为 group => lang key。
     *
     * @var array<string, string>
     */
    private const GROUPS = [
        'basic'   => 'messages.config_group_basic',
        'email'   => 'messages.config_group_email',
        'sms'     => 'messages.config_group_sms',
        'storage' => 'messages.config_group_storage',
        'payment' => 'messages.config_group_payment',
    ];

    #[Inject]
    protected SystemConfigRepository $systemConfigRepository;

    /** 读启用配置的值（已按 config_type 转换，走缓存）。 */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->systemConfigRepository->getConfigValue($key, $default);
    }

    /**
     * 登录安全类的布尔开关（login_captcha 等）：只有明确的关闭值才算关，认不出的值一律取安全默认。
     * SystemConfig::convertValueByType() 的布尔解析是白名单（只认 '1'/'true'/'yes'/'on'），其余一概为假——
     * 对这类开关是 fail open：把值写坏就静默关掉了验证码。那个方法是共享的，不动；只在这里按原值收紧读法。
     * 配置不存在、被禁用或值为空时取 $default。
     */
    public function isSecuritySwitchOn(string $key, bool $default = true): bool
    {
        $raw = strtolower(trim((string) $this->systemConfigRepository->getRawConfigValue($key)));

        return $raw === '' ? $default : !in_array($raw, self::SWITCH_OFF_VALUES, true);
    }

    /** @return array<string, string> group => 当前语言的名称 */
    public function getConfigGroups(): array
    {
        $groups = [];
        foreach (self::GROUPS as $group => $langKey) {
            $groups[$group] = lang($langKey);
        }

        return $groups;
    }

    /**
     * 某分组的启用配置原始行（config_value 不做类型转换，契约如此）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getConfigsByGroup(string $group): array
    {
        return $this->systemConfigRepository->getRowsByGroup($group);
    }

    /**
     * 单条配置原始行（不限状态）。不存在抛 business.config_not_found（code 400，照 TP8）。
     *
     * @return array<string, mixed>
     */
    public function getConfigById(int $id): array
    {
        return $this->systemConfigRepository->find($id) ?? throw new BusinessException(lang('business.config_not_found'));
    }

    public function updateConfig(int $id, mixed $value): bool
    {
        $stored = $this->serializeValue($this->getConfigById($id), $value);
        $this->runInTransaction(function () use ($id, $stored): void {
            $this->systemConfigRepository->update($id, ['config_value' => $stored]);
            $this->afterCommit(fn () => $this->systemConfigRepository->forgetCache());
        });

        return true;
    }

    /**
     * 按 config_key 批量更新；任一键不存在则整批回滚（BusinessException）。
     *
     * @param list<array{config_key: string, config_value: mixed}> $configs
     */
    public function batchUpdateConfigs(array $configs): bool
    {
        $this->runInTransaction(function () use ($configs): void {
            foreach ($configs as $item) {
                $key = $item['config_key'];
                $config = $this->systemConfigRepository->findByKey($key)
                    ?? throw new BusinessException(lang('business.config_key_not_found', ['key' => $key]));
                $this->systemConfigRepository->updateValueByKey($key, $this->serializeValue($config, $item['config_value']));
            }
            $this->afterCommit(fn () => $this->systemConfigRepository->forgetCache());
        });

        return true;
    }

    /** 只清配置缓存（spec §1.1 第 4 条）。 */
    public function clearConfigCache(): void
    {
        $this->systemConfigRepository->forgetCache();
    }

    /**
     * 前端启动配置（契约 §4.1）：公开且启用的配置的扁平映射（已按 config_type 转换），再排除凭据类键。
     *
     * @return array<string, mixed>
     */
    public function getGlobalConfigs(): array
    {
        return array_filter(
            $this->systemConfigRepository->getPublicConfigs(),
            static fn (int|string $key): bool => !self::isSensitiveKey((string) $key),
            ARRAY_FILTER_USE_KEY
        );
    }

    public static function isSensitiveKey(string $key): bool
    {
        return preg_match(self::SENSITIVE_KEY_PATTERN, $key) === 1;
    }

    /**
     * 把请求值序列化成库里存的字符串：
     *   - json：本身是合法 JSON 的非空字符串原样存；其余一律 json_encode。
     *     前端 handleSave 会先 JSON.stringify，TP8 再 json_encode 就会双重编码。
     *   - 其它类型：布尔转 '1'/'0'，null 转 ''，标量转字符串；数组或对象视为格式错误。
     *
     * @param array<string, mixed> $config
     */
    private function serializeValue(array $config, mixed $value): string
    {
        $this->assertUploadSizeWithinPackageLimit($config, $value);

        if ((string) $config['config_type'] === 'json') {
            if (is_string($value) && trim($value) !== '' && json_validate($value)) {
                return $value;
            }
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

            return $encoded !== false ? $encoded : throw $this->invalidValue($config);
        }

        return match (true) {
            is_bool($value)   => $value ? '1' : '0',
            $value === null   => '',
            is_scalar($value) => (string) $value,
            default           => throw $this->invalidValue($config),
        };
    }

    /** @param array<string, mixed> $config */
    private function invalidValue(array $config): BusinessException
    {
        return new BusinessException(lang('business.config_value_invalid', ['key' => (string) $config['config_key']]));
    }

    /**
     * 上传大小上限（Task 7 ruling 1）：`storage_upload_max_size` / `storage_image_max_size`
     * 不能被改到 Workerman 的 `max_package_size` 都装不下的值，否则请求在应用代码跑之前就
     * 被断开连接，管理员改完配置后看到的是一片「上传全部失败、没有任何错误提示」。
     * 非数字值不在这里挡（交给后面的类型序列化处理），只挡「数字但太大」这一种情形。
     *
     * @param array<string, mixed> $config
     */
    private function assertUploadSizeWithinPackageLimit(array $config, mixed $value): void
    {
        $key = (string) $config['config_key'];
        if (!in_array($key, self::UPLOAD_SIZE_CONFIG_KEYS, true) || !is_numeric($value)) {
            return;
        }

        $maxMb = $this->maxUploadSizeMb();
        if ((int) $value > $maxMb) {
            throw new BusinessException(lang('business.upload_size_exceeds_package_limit', ['key' => $key, 'max' => $maxMb]));
        }
    }

    /**
     * 管理员能填的上传大小上限（MB）：由 `config('server.max_package_size')` 减去协议开销余量
     * 换算得到，两处数字不需要分别维护，`max_package_size` 一旦调整，这里跟着变。
     */
    private function maxUploadSizeMb(): int
    {
        $packageBytes = (int) config('server.max_package_size', 10 * 1024 * 1024);

        return max(1, intdiv($packageBytes - self::UPLOAD_SIZE_PACKAGE_BUFFER_BYTES, 1024 * 1024));
    }
}
