<?php

declare(strict_types=1);

namespace tests\RedLine;

use core\wechat\WxMsgCrypt;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\Support\ApiTestCase;

/**
 * 红线（M6c spec §5.1）：GET /api/wechat/serve 是微信服务器 URL 接入的公开端点，唯一身份凭证就是
 * Token 算出的 signature。缺签、错签、Token 未配置（空串）——任何一种都不能把 echostr 回显出去，
 * 否则任何人都能用 `?echostr=probe` 冒充接入校验、确认本站在听。
 *
 * HTTP 200 + 空 body（不是 JSON 信封、也不是 success）：微信把非 echostr 原文当成校验失败。
 * 正向对照 test_valid_signature_echoes_probe_exactly 证明失败来自验签，而不是夹具本身发不通。
 */
final class Test36_WechatServeEchostrRequiresSignatureTest extends ApiTestCase
{
    private const TOKEN = 'tok36';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setConfig('wechat_official_app_id', 'wxRL36');
        $this->setConfig('wechat_official_token', self::TOKEN);
        $this->setConfig('wechat_official_aes_key', 'a2tra2tra2tra2tra2tra2tra2tra2tra2tra2tra2s');
        $this->setConfig('wechat_official_encrypt_type', '1');
    }

    /** @return array<string, array{string}> */
    public static function echostrForgeries(): array
    {
        return [
            '缺少 signature' => ['missing_signature'],
            '错误 signature' => ['wrong_signature'],
            'Token 为空'     => ['empty_token'],
        ];
    }

    #[DataProvider('echostrForgeries')]
    public function test_echostr_is_not_returned_without_valid_configured_signature(string $case): void
    {
        $validSignature = WxMsgCrypt::urlSignature(self::TOKEN, '1', '2');
        if ($case === 'empty_token') {
            $this->setConfig('wechat_official_token', '');
        }

        $uri = match ($case) {
            'missing_signature' => '/api/wechat/serve?echostr=probe',
            'wrong_signature'   => '/api/wechat/serve?signature=bad&timestamp=1&nonce=2&echostr=probe',
            // 签名按「清空前的 Token」计算：证明失败来自 Token 空，不是 query 少字段
            'empty_token'       => '/api/wechat/serve?signature=' . $validSignature . '&timestamp=1&nonce=2&echostr=probe',
        };

        $response = $this->get($uri);

        $this->assertSame(200, $response->status(), "{$case}：验签失败应 HTTP 200 空 body，不能走统一 JSON 信封");
        $this->assertSame('', $response->body(), "{$case}：body 必须是空串");
        $this->assertStringNotContainsString('echostr', $response->body(), "{$case}：不得回显参数名 echostr");
        $this->assertStringNotContainsString('probe', $response->body(), "{$case}：不得回显 echostr 的值");
    }

    public function test_valid_signature_echoes_probe_exactly(): void
    {
        $timestamp = '1700000000';
        $nonce = 'n36';
        $signature = WxMsgCrypt::urlSignature(self::TOKEN, $timestamp, $nonce);

        $response = $this->get('/api/wechat/serve', compact('signature', 'timestamp', 'nonce') + ['echostr' => 'probe']);

        $this->assertSame(200, $response->status(), '正向对照：签名正确必须回显 echostr，否则上面的失败用例证明不了什么。响应：' . $response->body());
        $this->assertSame('probe', $response->body());
    }
}
