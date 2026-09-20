<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\repository\wechat\WechatAutoReplyRepository;
use core\wechat\WxMsgCrypt;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\Support\ApiTestCase;

/**
 * 红线（M6c spec §5.1）：POST /api/wechat/serve 是微信消息回调，公开、不走统一响应体。错 signature、
 * 错 msg_signature、密文 appId 不是本公众号——任何一种都不能进入自动回复，body 里不能出现
 * `<Content>`（明文回复的标志）。
 *
 * 每组伪造都先种一条能命中的关键词规则；正向对照 test_valid_plain_post_replies_content 证明同一套
 * 夹具在验签通过时**会**写出 `<Content>`，所以失败确实来自验签，不是规则没种上。
 */
final class Test37_WechatServeForgedPostRejectedTest extends ApiTestCase
{
    private const TOKEN = 'tok37';

    private const APP_ID = 'wxRL37';

    private const AES_KEY = 'a2tra2tra2tra2tra2tra2tra2tra2tra2tra2tra2s';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setConfig('wechat_official_app_id', self::APP_ID);
        $this->setConfig('wechat_official_token', self::TOKEN);
        $this->setConfig('wechat_official_aes_key', self::AES_KEY);
        $this->setConfig('wechat_official_encrypt_type', '1');
    }

    /** @return array<string, array{string}> */
    public static function forgedPosts(): array
    {
        return [
            '错误 signature'        => ['wrong_signature'],
            '错误 msg_signature'    => ['wrong_msg_signature'],
            '密文 appId 不是本公众号' => ['appid_mismatch'],
        ];
    }

    #[DataProvider('forgedPosts')]
    public function test_forged_post_never_contains_content(string $case): void
    {
        $this->rule(['keyword' => 'hello', 'content' => '世界']);
        $plain = $this->xml(['MsgType' => 'text', 'Content' => 'hello']);

        $response = match ($case) {
            'wrong_signature' => $this->postRaw(
                '/api/wechat/serve?signature=bad&timestamp=1&nonce=2',
                $plain,
                ['Content-Type' => 'application/xml']
            ),
            'wrong_msg_signature' => $this->postCipher($plain, 'bad-msg-signature'),
            'appid_mismatch'      => $this->postCipherAppId('wrong-app'),
        };

        $this->assertSame(200, $response->status(), "{$case}：伪造回调应 HTTP 200，不能抛成 500");
        $this->assertSame('', $response->body(), "{$case}：验签/解密失败必须空 body，不能回 success 或加密信封");
        $this->assertStringNotContainsString('<Content>', $response->body(), "{$case}：伪造回调不得进入自动回复");
    }

    public function test_valid_plain_post_replies_content(): void
    {
        $this->rule(['keyword' => 'hello', 'content' => '世界']);
        $timestamp = '1700000000';
        $nonce = 'n37';
        $signature = WxMsgCrypt::urlSignature(self::TOKEN, $timestamp, $nonce);

        $response = $this->postRaw(
            '/api/wechat/serve?' . http_build_query(compact('signature', 'timestamp', 'nonce')),
            $this->xml(['MsgType' => 'text', 'Content' => 'hello']),
            ['Content-Type' => 'application/xml']
        );

        $this->assertSame(200, $response->status(), '正向对照：签名正确必须回复，否则上面的失败用例证明不了什么。响应：' . $response->body());
        $this->assertStringContainsString('<Content><![CDATA[世界]]>', $response->body());
    }

    /** @param array<string, mixed> $overrides */
    private function rule(array $overrides): void
    {
        $row = (new WechatAutoReplyRepository())->create(array_merge([
            'type' => WechatAutoReplyRepository::TYPE_KEYWORD,
            'keyword' => 'hello',
            'match_type' => WechatAutoReplyRepository::MATCH_EXACT,
            'content' => 'reply',
            'status' => WechatAutoReplyRepository::STATUS_ENABLED,
            'sort_order' => 0,
        ], $overrides));
        $this->track('wechat_auto_replies', (int) $row['id']);
    }

    /** @param array<string, string> $fields */
    private function xml(array $fields): string
    {
        $fields = ['ToUserName' => 'to-user', 'FromUserName' => 'from-user'] + $fields;
        $xml = '<xml>';
        foreach ($fields as $name => $value) {
            $xml .= "<{$name}><![CDATA[{$value}]]></{$name}>";
        }

        return $xml . '</xml>';
    }

    private function postCipher(string $plain, string $msgSignature): \tests\Support\TestResponse
    {
        $this->setConfig('wechat_official_encrypt_type', '3');
        $timestamp = '1700000000';
        $nonce = 'safe-nonce-37';
        $cipher = WxMsgCrypt::encrypt(self::AES_KEY, $plain, self::APP_ID);
        $this->assertNotNull($cipher, '前置条件：夹具必须能加密，否则本用例什么也没验证');

        return $this->postRaw(
            '/api/wechat/serve?' . http_build_query([
                'timestamp' => $timestamp, 'nonce' => $nonce, 'encrypt_type' => 'aes', 'msg_signature' => $msgSignature,
            ]),
            '<xml><Encrypt><![CDATA[' . $cipher . ']]></Encrypt></xml>',
            ['Content-Type' => 'application/xml']
        );
    }

    private function postCipherAppId(string $appId): \tests\Support\TestResponse
    {
        $this->setConfig('wechat_official_encrypt_type', '3');
        $timestamp = '1700000000';
        $nonce = 'safe-nonce-37';
        $plain = $this->xml(['MsgType' => 'text', 'Content' => 'hello']);
        $cipher = WxMsgCrypt::encrypt(self::AES_KEY, $plain, $appId);
        $this->assertNotNull($cipher, '前置条件：夹具必须能加密，否则本用例什么也没验证');
        $msgSignature = WxMsgCrypt::msgSignature(self::TOKEN, $timestamp, $nonce, $cipher);

        return $this->postRaw(
            '/api/wechat/serve?' . http_build_query([
                'timestamp' => $timestamp, 'nonce' => $nonce, 'encrypt_type' => 'aes', 'msg_signature' => $msgSignature,
            ]),
            '<xml><Encrypt><![CDATA[' . $cipher . ']]></Encrypt></xml>',
            ['Content-Type' => 'application/xml']
        );
    }
}
