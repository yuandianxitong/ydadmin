<?php

declare(strict_types=1);

namespace core\helper;

/**
 * 操作日志参数脱敏（spec §6.2，移植 Saas）：命中的键保留、值替换为 '***'，递归处理嵌套数组。
 * 审计能看出「提交过密码字段」，但看不到值。
 *
 * 判定（键名不区分大小写）：
 * - 精确键：password、old_password、new_password、captcha、token 等（SENSITIVE_KEYS）；
 * - 片段：键名含 password、secret、access_key、private、token、api_key、mch_key、aes_key 任一；
 * - 同一层的 config_key 看起来是凭据时，其 config_value 也脱敏（config/batch-update 的 configs[]）。
 * 纯静态方法，无状态。
 */
final class SensitiveDataMasker
{
    public const MASK = '***';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'confirm_password',
        'old_password',
        'new_password',
        'repeat_password',
        'captcha',
        'captcha_key',
        'token',
        'access_token',
        'refresh_token',
        'secret',
    ];

    /** @var list<string> */
    private const SENSITIVE_FRAGMENTS = ['password', 'secret', 'access_key', 'private', 'token', 'api_key', 'mch_key', 'aes_key'];

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public static function mask(array $data): array
    {
        $configValueIsSecret = isset($data['config_key']) && is_string($data['config_key']) && self::isSensitiveKey($data['config_key']);
        $masked = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && (self::isSensitiveKey($key) || ($configValueIsSecret && $key === 'config_value'))) {
                $masked[$key] = self::MASK;
                continue;
            }
            $masked[$key] = is_array($value) ? self::mask($value) : $value;
        }

        return $masked;
    }

    public static function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);
        if (in_array($key, self::SENSITIVE_KEYS, true)) {
            return true;
        }
        foreach (self::SENSITIVE_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
