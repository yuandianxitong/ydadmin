<?php

declare(strict_types=1);

namespace core\sms\driver;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use core\exception\BusinessException;
use core\sms\SmsInterface;
use Darabonba\OpenApi\Models\Config;
use support\Log;

/**
 * 阿里云短信驱动（SDK：alibabacloud/dysmsapi-20170525，命名空间 AlibabaCloud\SDK\Dysmsapi\V20170525）。
 *
 * endpoint 固定 dysmsapi.aliyuncs.com：国内短信是中心化服务，没有地域维度（SDK 自带的 endpointMap 里
 * 除少数海外地域外全部指向这个域名），所以不像 OSS 那样需要一个 region 配置项。
 *
 * 失败分两类（spec §7.3）：凭据不全在**构造时**抛固定文案；网关拒绝（Code != OK）与网络故障把原文写日志、
 * 对外统一 `sms_send_failed`。日志里的手机号打码——短信日志是最容易被批量导出的那一类日志。
 */
final class AliyunSmsDriver implements SmsInterface
{
    private const ENDPOINT = 'dysmsapi.aliyuncs.com';

    private readonly Dysmsapi $client;

    private readonly string $signName;

    /** @param array{access_key: string, access_secret: string, sign_name: string, sdk_app_id: string} $config */
    public function __construct(array $config)
    {
        foreach (['access_key', 'access_secret', 'sign_name'] as $required) {
            // trim 之后再判断：管理端是文本输入框，纯空格必须当「没填」处理
            if (trim((string) ($config[$required] ?? '')) === '') {
                throw new BusinessException(lang('business.sms_config_incomplete_aliyun'));
            }
        }

        $this->signName = trim($config['sign_name']);
        $this->client = new Dysmsapi(new Config([
            'accessKeyId'     => trim($config['access_key']),
            'accessKeySecret' => trim($config['access_secret']),
            'endpoint'        => self::ENDPOINT,
        ]));
    }

    public function send(string $mobile, string $templateId, array $vars): void
    {
        $templateId = trim($templateId);
        if ($templateId === '') {
            throw new BusinessException(lang('business.sms_template_missing'));
        }

        $request = new SendSmsRequest([
            'phoneNumbers'  => $mobile,
            'signName'      => $this->signName,
            'templateCode'  => $templateId,
            'templateParam' => (string) json_encode($vars, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);

        try {
            $response = $this->client->sendSms($request);
        } catch (\Throwable $e) {
            Log::error('阿里云短信发送失败（网关异常）', [
                'mobile'   => self::mask($mobile),
                'template' => $templateId,
                'error'    => $e->getMessage(),
            ]);

            throw new BusinessException(lang('business.sms_send_failed'));
        }

        $body = $response->body;
        // 阿里云的业务码在 200 响应体里：Code 不是 OK 就是发失败（余额不足、签名未审核、模板不匹配……）
        // SDK 的模型属性没有原生类型声明（只有 @var string 文档注解），实际未初始化时是 null，
        // 所以这里显式 (string) 转换兜底，而不是依赖 phpstan 认为「已经是 string」的 ?? 判断
        // （phpstan 按文档注解推断为非空 string，对 ?? 会报 nullCoalesce.property）。
        if (strtoupper((string) $body->code) !== 'OK') {
            Log::error('阿里云短信发送失败（网关拒绝）', [
                'mobile'   => self::mask($mobile),
                'template' => $templateId,
                'code'     => (string) $body->code,
                'message'  => (string) $body->message,
                'biz_id'   => (string) $body->bizId,
            ]);

            throw new BusinessException(lang('business.sms_send_failed'));
        }
    }

    /** 日志里的手机号打码：138****8000。 */
    private static function mask(string $mobile): string
    {
        return strlen($mobile) === 11 ? substr($mobile, 0, 3) . '****' . substr($mobile, -4) : '***';
    }
}
