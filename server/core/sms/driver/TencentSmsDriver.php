<?php

declare(strict_types=1);

namespace core\sms\driver;

use core\exception\BusinessException;
use core\sms\SmsInterface;
use support\Log;

/**
 * 腾讯云短信驱动（SDK：tencentcloud/sms，**默认不安装**）。
 *
 * 计划「设计决定」第 8 条：两个 SDK 都装会把依赖树撑大，所以只引入阿里云，腾讯云的驱动骨架先备好，
 * 运维真要切过去时执行 `composer require tencentcloud/sms` 即可，本文件一行都不用改。
 *
 * 🔴 正因为 SDK 不在依赖树里，本文件不得 `use TencentCloud\…`：那些类 autoload 解析不到，phpstan 也
 * 认不出来。这里按类名字符串 `class_exists` 判定 + 动态构造；对 SDK 对象的调用一律经 `call()` 走
 * **变量方法名**——静态分析无法解析变量方法名，也就不会报 method.notFound，不需要往 phpstan.neon
 * 里加 ignoreErrors（那种忽略项在装上 SDK 之后反而会变成「未匹配的忽略」报错）。
 *
 * 失败分类与阿里云驱动一致（spec §7.3）：配置不全给固定文案、网关报错写日志 + 统一文案、手机号打码。
 * 网关拒绝时的日志载荷只取 Code/Message（见 gatewayRejectionLogContext()），绝不落原始响应体——
 * 腾讯云 SendStatusSet 里的 PhoneNumber 是未打码的完整手机号，落原始响应等于让 mobile 字段的打码形同虚设。
 */
final class TencentSmsDriver implements SmsInterface
{
    /** 国内短信不按地域计费，SDK 只是要一个合法地域值。 */
    private const REGION = 'ap-guangzhou';

    private const CLIENT_CLASS = 'TencentCloud\\Sms\\V20210111\\SmsClient';

    private const CREDENTIAL_CLASS = 'TencentCloud\\Common\\Credential';

    private const REQUEST_CLASS = 'TencentCloud\\Sms\\V20210111\\Models\\SendSmsRequest';

    private readonly string $secretId;

    private readonly string $secretKey;

    private readonly string $signName;

    private readonly string $sdkAppId;

    /** @param array{access_key: string, access_secret: string, sign_name: string, sdk_app_id: string} $config */
    public function __construct(array $config)
    {
        foreach (['access_key', 'access_secret', 'sign_name', 'sdk_app_id'] as $required) {
            // trim 之后再判断：管理端是文本输入框，纯空格必须当「没填」处理
            if (trim((string) ($config[$required] ?? '')) === '') {
                throw new BusinessException(lang('business.sms_config_incomplete_tencent'));
            }
        }
        // 判定顺序是「先配置、后 SDK」：配置本来就没填全时，先说「去填配置」比先说「去装 SDK」
        // 更接近运维真正要做的事；配置填全了才提示装 SDK。
        if (!class_exists(self::CLIENT_CLASS)) {
            throw new BusinessException(lang('business.sms_tencent_sdk_missing'));
        }

        $this->secretId = trim($config['access_key']);
        $this->secretKey = trim($config['access_secret']);
        $this->signName = trim($config['sign_name']);
        $this->sdkAppId = trim($config['sdk_app_id']);
    }

    public function send(string $mobile, string $templateId, array $vars): void
    {
        $templateId = trim($templateId);
        if ($templateId === '') {
            throw new BusinessException(lang('business.sms_template_missing'));
        }

        $request = self::instantiate(self::REQUEST_CLASS);
        // fromJsonString 是腾讯云 SDK 给所有请求模型的统一入口：比逐个赋公有属性少一堆动态属性访问
        self::call($request, 'fromJsonString', (string) json_encode([
            'PhoneNumberSet'   => ['+86' . $mobile],
            'SmsSdkAppId'      => $this->sdkAppId,
            'SignName'         => $this->signName,
            'TemplateId'       => $templateId,
            'TemplateParamSet' => array_values(array_map(strval(...), $vars)),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $client = self::instantiate(
            self::CLIENT_CLASS,
            self::instantiate(self::CREDENTIAL_CLASS, $this->secretId, $this->secretKey),
            self::REGION
        );

        try {
            $response = self::call($client, 'SendSms', $request);
            $raw = is_object($response) ? self::call($response, 'toJsonString') : null;
        } catch (\Throwable $e) {
            Log::error('腾讯云短信发送失败（网关异常）', [
                'mobile'   => self::mask($mobile),
                'template' => $templateId,
                'error'    => $e->getMessage(),
            ]);

            throw new BusinessException(lang('business.sms_send_failed'));
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $status = is_array($decoded) ? ($decoded['SendStatusSet'][0] ?? null) : null;
        // 腾讯云的逐号发送结果在 SendStatusSet 里，Code 为 'Ok' 才算成功
        if (!is_array($status) || (string) ($status['Code'] ?? '') !== 'Ok') {
            Log::error(
                '腾讯云短信发送失败（网关拒绝）',
                self::gatewayRejectionLogContext($mobile, $templateId, $status)
            );

            throw new BusinessException(lang('business.sms_send_failed'));
        }
    }

    /**
     * 网关拒绝日志的载荷：只取 Code/Message，绝不整条落 SendStatusSet 或原始响应体——
     * SendStatusSet 里的 PhoneNumber 是未打码的完整 E.164 手机号，落进日志就等于把
     * 已经打码的 `mobile` 字段白打了码（与 AliyunSmsDriver 只记 code/message/bizId、
     * 从不落原始响应体同一条纪律）。
     *
     * 抽成独立的纯函数（不直接调用 Log::error）是为了能在腾讯云 SDK 未安装、
     * send() 整条网络路径不可达时，仍能单独反射调用它验证日志载荷不泄漏手机号
     * （见 SmsDriverConfigTest::test_gateway_rejection_log_context_never_leaks_the_raw_phone_number）。
     *
     * @param array<string, mixed>|null $status 腾讯云 SendStatusSet 的单条记录，取不到时传 null
     * @return array<string, string>
     */
    private static function gatewayRejectionLogContext(string $mobile, string $templateId, ?array $status): array
    {
        return [
            'mobile'   => self::mask($mobile),
            'template' => $templateId,
            'code'     => (string) ($status['Code'] ?? ''),
            'message'  => (string) ($status['Message'] ?? ''),
        ];
    }

    /** SDK 不在依赖树里，只能按类名字符串动态构造；返回值故意是 object。 */
    private static function instantiate(string $class, mixed ...$args): object
    {
        if (!class_exists($class)) {
            throw new BusinessException(lang('business.sms_tencent_sdk_missing'));
        }

        return new $class(...$args);
    }

    /** 调用 SDK 对象的方法。方法名走变量，静态分析不解析——见类注释。 */
    private static function call(object $target, string $method, mixed ...$args): mixed
    {
        /** @var callable $callable */
        $callable = [$target, $method];

        return $callable(...$args);
    }

    /** 日志里的手机号打码：138****8000。 */
    private static function mask(string $mobile): string
    {
        return strlen($mobile) === 11 ? substr($mobile, 0, 3) . '****' . substr($mobile, -4) : '***';
    }
}
