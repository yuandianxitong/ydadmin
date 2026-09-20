<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\repository\wechat\WechatAutoReplyRepository;
use core\wechat\WxMsgCrypt;
use Monolog\Handler\TestHandler;
use support\Log;
use tests\Support\ApiTestCase;

/**
 * 红线（M6c spec §5.1）：公众号消息回调的成功路径不得把 Token、EncodingAESKey、用户正文、
 * access_token 写进 HTTP 响应或应用日志。serve 不调微信 HTTP、不投队列；成功时也不该把密钥或
 * 用户原话回显到应答 XML / Monolog。
 *
 * Token / AESKey / 用户正文用固定诱饵，方便按值扫响应体与 TestHandler 记录（与 Test31、Test33 同理）。
 */
final class Test38_WechatServeNoSecretLeakTest extends ApiTestCase
{
    private const TOKEN = 'leak-token-value';

    private const AES_KEY = 'LEAKAESKEYLEAKAESKEYLEAKAESKEYLEAKAESKEY123';

    private const USER_TEXT = 'LEAK-MSG';

    private const REPLY = 'RL38-ACK';

    private TestHandler $logs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setConfig('wechat_official_app_id', 'wxRL38');
        $this->setConfig('wechat_official_token', self::TOKEN);
        $this->setConfig('wechat_official_aes_key', self::AES_KEY);
        $this->setConfig('wechat_official_encrypt_type', '1');
        $this->logs = new TestHandler();
        Log::channel()->pushHandler($this->logs);
    }

    protected function tearDown(): void
    {
        Log::channel()->popHandler();
        parent::tearDown();
    }

    public function test_successful_post_does_not_leak_token_aes_key_user_text_or_access_token(): void
    {
        $this->rule(['keyword' => self::USER_TEXT, 'content' => self::REPLY]);
        $timestamp = '1700000000';
        $nonce = 'n38';
        $signature = WxMsgCrypt::urlSignature(self::TOKEN, $timestamp, $nonce);

        $response = $this->postRaw(
            '/api/wechat/serve?' . http_build_query(compact('signature', 'timestamp', 'nonce')),
            $this->xml(['MsgType' => 'text', 'Content' => self::USER_TEXT]),
            ['Content-Type' => 'application/xml']
        );

        $this->assertSame(200, $response->status(), '前置条件：必须走成功路径。响应：' . $response->body());
        $this->assertStringContainsString('<Content><![CDATA[' . self::REPLY . ']]>', $response->body(), '前置条件：关键词必须命中，否则下面的泄漏断言是空转');

        $records = json_encode($this->logs->getRecords(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        foreach ([self::TOKEN, self::AES_KEY, self::USER_TEXT, 'access_token'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->body(), "响应体不得出现 {$secret}");
            $this->assertStringNotContainsString($secret, $records, "TestHandler 记录不得出现 {$secret}");
        }
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
}
