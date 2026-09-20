<?php

declare(strict_types=1);

namespace tests\Feature\Wechat;

use app\api\controller\wechat\WechatController;
use app\repository\wechat\WechatAutoReplyRepository;
use app\service\wechat\AutoReplyService;
use app\service\wechat\WechatServeService;
use core\wechat\WechatConfigResolver;
use core\wechat\WxMsgCrypt;
use Monolog\Handler\TestHandler;
use support\Container;
use support\Log;
use tests\Support\ApiTestCase;

final class WechatServeApiTest extends ApiTestCase
{
    private const TOKEN = 'tok';
    private const APP_ID = 'wxAPP';
    private const AES_KEY = 'a2tra2tra2tra2tra2tra2tra2tra2tra2tra2tra2s';

    private TestHandler $logs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setConfig('wechat_official_app_id', self::APP_ID);
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

    public function test_get_rejects_missing_bad_and_unconfigured_signatures_without_echoing_probe(): void
    {
        foreach ([
            '/api/wechat/serve?echostr=probe',
            '/api/wechat/serve?signature=bad&timestamp=1&nonce=2&echostr=probe',
        ] as $uri) {
            $response = $this->get($uri);
            $this->assertSame(200, $response->status());
            $this->assertSame('', $response->body());
            $this->assertStringNotContainsString('probe', $response->body());
        }

        $this->setConfig('wechat_official_token', '');
        $response = $this->get('/api/wechat/serve?signature=bad&timestamp=1&nonce=2&echostr=probe');
        $this->assertSame('', $response->body());
        $this->assertStringNotContainsString('probe', $response->body());
    }

    public function test_get_returns_echostr_exactly_for_valid_signature(): void
    {
        $timestamp = '1700000000';
        $nonce = 'n1';
        $signature = WxMsgCrypt::urlSignature(self::TOKEN, $timestamp, $nonce);

        $response = $this->get('/api/wechat/serve', compact('signature', 'timestamp', 'nonce') + ['echostr' => 'probe']);

        $this->assertSame(200, $response->status());
        $this->assertSame('text/plain; charset=utf-8', $response->header('Content-Type'));
        $this->assertSame('probe', $response->body());
    }

    public function test_plain_text_and_click_messages_match_keyword_and_swap_users(): void
    {
        $this->rule(['keyword' => 'hello', 'content' => '世界']);
        foreach ([
            $this->xml(['MsgType' => 'text', 'Content' => 'hello']),
            $this->xml(['MsgType' => 'event', 'Event' => 'CLICK', 'EventKey' => 'hello']),
        ] as $xml) {
            $response = $this->postPlain($xml);
            $this->assertSame(200, $response->status());
            $this->assertStringContainsString('<ToUserName><![CDATA[from-user]]>', $response->body());
            $this->assertStringContainsString('<FromUserName><![CDATA[to-user]]>', $response->body());
            $this->assertStringContainsString('<Content><![CDATA[世界]]>', $response->body());
        }
    }

    public function test_subscribe_uses_only_subscribe_reply_including_qrscene_event_key(): void
    {
        $response = $this->postPlain($this->xml([
            'MsgType' => 'event', 'Event' => 'subscribe', 'EventKey' => 'qrscene_hello',
        ]));
        $this->assertSame('success', $response->body());

        $this->rule(['type' => WechatAutoReplyRepository::TYPE_SUBSCRIBE, 'keyword' => '', 'content' => '欢迎']);
        $response = $this->postPlain($this->xml([
            'MsgType' => 'event', 'Event' => 'SUBSCRIBE', 'EventKey' => 'qrscene_hello',
        ]));
        $this->assertStringContainsString('<Content><![CDATA[欢迎]]>', $response->body());
    }

    public function test_post_bad_signature_returns_empty_body_not_success(): void
    {
        $response = $this->postRaw(
            '/api/wechat/serve?signature=bad&timestamp=1&nonce=2',
            $this->xml(['MsgType' => 'text', 'Content' => 'hello']),
            ['Content-Type' => 'application/xml']
        );

        $this->assertSame(200, $response->status());
        $this->assertSame('', $response->body());
    }

    public function test_safe_mode_rejects_wrong_app_id_and_encrypts_valid_reply(): void
    {
        $this->setConfig('wechat_official_encrypt_type', '3');
        $this->rule(['keyword' => 'hello', 'content' => '世界']);
        $timestamp = '1700000000';
        $nonce = 'safe-nonce';
        $plain = $this->xml(['MsgType' => 'text', 'Content' => 'hello']);

        $wrongCipher = WxMsgCrypt::encrypt(self::AES_KEY, $plain, 'wrong-app');
        $this->assertNotNull($wrongCipher);
        $wrong = $this->postCipher($wrongCipher, $timestamp, $nonce);
        $this->assertSame('', $wrong->body());

        $cipher = WxMsgCrypt::encrypt(self::AES_KEY, $plain, self::APP_ID);
        $this->assertNotNull($cipher);
        $response = $this->postCipher($cipher, $timestamp, $nonce);
        $this->assertSame('application/xml; charset=utf-8', $response->header('Content-Type'));
        $envelope = simplexml_load_string($response->body());
        $this->assertNotFalse($envelope);
        $reply = WxMsgCrypt::decrypt(self::AES_KEY, (string) $envelope->Encrypt, self::APP_ID);
        $this->assertNotNull($reply);
        $this->assertStringContainsString('<Content><![CDATA[世界]]>', $reply);
    }

    public function test_matching_exception_returns_success_and_logs_no_secrets_or_user_content(): void
    {
        $originalAutoReply = Container::get(AutoReplyService::class);
        $originalServe = Container::get(WechatServeService::class);
        Container::set(AutoReplyService::class, new class () extends AutoReplyService {
            public function matchKeyword(string $keyword): ?string
            {
                throw new \RuntimeException('SQL TOKEN tok SECRET-USER-TEXT');
            }
        });
        Container::set(
            WechatServeService::class,
            new WechatServeService(Container::get(WechatConfigResolver::class), Container::get(AutoReplyService::class))
        );
        Container::set(WechatController::class, Container::make(WechatController::class));
        try {
            $response = $this->postPlain($this->xml(['MsgType' => 'text', 'Content' => 'SECRET-USER-TEXT']));
        } finally {
            Container::set(AutoReplyService::class, $originalAutoReply);
            Container::set(WechatServeService::class, $originalServe);
            Container::set(WechatController::class, Container::make(WechatController::class));
        }

        $this->assertSame('success', $response->body());
        $this->assertNotEmpty($this->logs->getRecords());
        $records = json_encode($this->logs->getRecords(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('SECRET-USER-TEXT', $records);
        $this->assertStringNotContainsString(self::TOKEN, $records);
        $this->assertStringNotContainsString('SQL', $records);
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

    private function postPlain(string $xml): \tests\Support\TestResponse
    {
        $timestamp = '1700000000';
        $nonce = 'plain-nonce';
        $signature = WxMsgCrypt::urlSignature(self::TOKEN, $timestamp, $nonce);

        return $this->postRaw(
            '/api/wechat/serve?' . http_build_query(compact('signature', 'timestamp', 'nonce')),
            $xml,
            ['Content-Type' => 'application/xml']
        );
    }

    private function postCipher(string $cipher, string $timestamp, string $nonce): \tests\Support\TestResponse
    {
        $msgSignature = WxMsgCrypt::msgSignature(self::TOKEN, $timestamp, $nonce, $cipher);
        $body = '<xml><Encrypt><![CDATA[' . $cipher . ']]></Encrypt></xml>';

        return $this->postRaw(
            '/api/wechat/serve?' . http_build_query([
                'timestamp' => $timestamp, 'nonce' => $nonce, 'encrypt_type' => 'aes', 'msg_signature' => $msgSignature,
            ]),
            $body,
            ['Content-Type' => 'application/xml']
        );
    }
}
