<?php

declare(strict_types=1);

namespace core\sms;

/**
 * 短信发送驱动（spec §3、§7）。与 core\storage\StorageInterface 同构：core 只负责「把一条短信交给网关」，
 * 验证码的生成、缓存、校验、限流是业务，在 app\service\user\SmsCodeService，不进 core。
 *
 * 失败一律抛 core\exception\BusinessException（spec §7.3 的两类）：
 *   - 凭据/签名没配全：构造时就抛固定文案（按驱动区分阿里云 / 腾讯云），绝不带着空 AccessKey 去请求网关；
 *   - 网关拒绝或网络故障：原文写日志，对外只给统一文案——网关错误里常带 AccessKey 片段与签名细节。
 */
interface SmsInterface
{
    /**
     * @param string $mobile     11 位国内手机号，不带 +86（腾讯云驱动内部自己补）
     * @param string $templateId 模板 id（阿里云 TemplateCode / 腾讯云 TemplateId）
     * @param array<string, string> $vars 模板变量，如 ['code' => '123456']
     * @throws \core\exception\BusinessException 配置不全、模板为空、网关拒绝或网络故障
     */
    public function send(string $mobile, string $templateId, array $vars): void;
}
