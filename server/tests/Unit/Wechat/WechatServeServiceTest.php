<?php

declare(strict_types=1);

namespace tests\Unit\Wechat;

use app\service\wechat\AutoReplyService;
use app\service\wechat\WechatServeService;
use core\contract\ConfigValueReader;
use core\wechat\WechatConfigResolver;
use core\wechat\WxMsgCrypt;
use Monolog\Handler\TestHandler;
use support\Log;
use tests\TestCase;

final class WechatServeServiceTest extends TestCase
{
    private const TOKEN = 'tok';
    private const APP_ID = 'wxAPP';
    private const AES_KEY = 'a2tra2tra2tra2tra2tra2tra2tra2tra2tra2tra2s';

    private TestHandler $logs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logs = new TestHandler();
        Log::channel()->pushHandler($this->logs);
    }

    protected function tearDown(): void
    {
        Log::channel()->popHandler();
        parent::tearDown();
    }

    public function test_get_only_echoes_for_configured_valid_signature(): void
    {
        $service = $this->service();
        $signature = WxMsgCrypt::urlSignature(self::TOKEN, '1', '2');

        $this->assertSame('', $service->handleGet('', '1', '2', 'probe')->body);
        $this->assertSame('', $service->handleGet('bad', '1', '2', 'probe')->body);
        $this->assertSame('probe', $service->handleGet($signature, '1', '2', 'probe')->body);
        $this->assertSame('', $this->service(['wechat_official_token' => ''])->handleGet($signature, '1', '2', 'probe')->body);
    }

    public function test_encrypt_modes_reject_the_wrong_transport_and_bad_xml(): void
    {
        $plain = $this->xml(['MsgType' => 'text', 'Content' => 'hello']);
        $signature = WxMsgCrypt::urlSignature(self::TOKEN, '1', '2');
        $outer = '<xml><Encrypt><![CDATA[cipher]]></Encrypt></xml>';

        $this->assertSame('', $this->service(['wechat_official_encrypt_type' => '1'])
            ->handlePost($signature, '1', '2', $outer, 'aes', 'bad')->body);
        $this->assertSame('', $this->service(['wechat_official_encrypt_type' => '3'])
            ->handlePost($signature, '1', '2', $plain, '', '')->body);
        $this->assertSame('', $this->service()
            ->handlePost($signature, '1', '2', '<xml>', '', '')->body);
    }

    public function test_compatibility_mode_accepts_plain_or_cipher_and_detects_encrypt_node(): void
    {
        $auto = $this->autoReply(keywordReply: 'reply');
        $service = $this->service(['wechat_official_encrypt_type' => '2'], $auto);
        $plain = $this->xml(['mSgTyPe' => 'text', 'cOnTeNt' => 'hello']);
        $signature = WxMsgCrypt::urlSignature(self::TOKEN, '1', '2');
        $this->assertStringContainsString('reply', $service->handlePost($signature, '1', '2', $plain, '', '')->body);

        $cipher = WxMsgCrypt::encrypt(self::AES_KEY, $plain, self::APP_ID);
        $this->assertNotNull($cipher);
        $msgSignature = WxMsgCrypt::msgSignature(self::TOKEN, '1', '2', $cipher);
        $ack = $service->handlePost('', '1', '2', '<xml><eNcRyPt>' . $cipher . '</eNcRyPt></xml>', '', $msgSignature);
        $this->assertSame('application/xml; charset=utf-8', $ack->contentType);
    }

    public function test_subscribe_never_matches_event_key_while_text_and_click_do(): void
    {
        $auto = $this->autoReply(keywordReply: 'keyword', subscribeReply: 'welcome');
        $service = $this->service([], $auto);
        $signature = WxMsgCrypt::urlSignature(self::TOKEN, '1', '2');

        $subscribe = $service->handlePost($signature, '1', '2', $this->xml([
            'MsgType' => 'event', 'Event' => 'subscribe', 'EventKey' => 'qrscene_hello',
        ]), '', '');
        $this->assertStringContainsString('welcome', $subscribe->body);
        $this->assertSame([], $auto->keywords);
        $this->assertSame(1, $auto->subscribeCalls);

        $service->handlePost($signature, '1', '2', $this->xml(['MsgType' => 'text', 'Content' => 'hello']), '', '');
        $service->handlePost($signature, '1', '2', $this->xml(['MsgType' => 'event', 'Event' => 'CLICK', 'EventKey' => 'button']), '', '');
        $this->assertSame(['hello', 'button'], $auto->keywords);
    }

    public function test_unsupported_or_empty_reply_returns_plain_success(): void
    {
        $service = $this->service([], $this->autoReply());
        $signature = WxMsgCrypt::urlSignature(self::TOKEN, '1', '2');

        foreach ([
            $this->xml(['MsgType' => 'image']),
            $this->xml(['MsgType' => 'event', 'Event' => 'unsubscribe']),
            $this->xml(['MsgType' => 'text', 'Content' => 'missing']),
        ] as $xml) {
            $ack = $service->handlePost($signature, '1', '2', $xml, '', '');
            $this->assertSame('success', $ack->body);
            $this->assertSame('text/plain; charset=utf-8', $ack->contentType);
        }
    }

    public function test_cipher_verification_decryption_and_app_id_failures_return_empty(): void
    {
        $service = $this->service(['wechat_official_encrypt_type' => '3'], $this->autoReply(keywordReply: 'reply'));
        $plain = $this->xml(['MsgType' => 'text', 'Content' => 'hello']);
        $cipher = WxMsgCrypt::encrypt(self::AES_KEY, $plain, self::APP_ID);
        $wrongAppCipher = WxMsgCrypt::encrypt(self::AES_KEY, $plain, 'wrong');
        $this->assertNotNull($cipher);
        $this->assertNotNull($wrongAppCipher);

        $this->assertSame('', $service->handlePost('', '1', '2', $this->encryptedXml($cipher), 'aes', 'bad')->body);
        $wrongSignature = WxMsgCrypt::msgSignature(self::TOKEN, '1', '2', $wrongAppCipher);
        $this->assertSame('', $service->handlePost('', '1', '2', $this->encryptedXml($wrongAppCipher), 'aes', $wrongSignature)->body);
    }

    public function test_cipher_reply_uses_generated_nonce_when_request_nonce_is_empty(): void
    {
        $service = $this->service(['wechat_official_encrypt_type' => '3'], $this->autoReply(keywordReply: 'reply'));
        $plain = $this->xml(['MsgType' => 'text', 'Content' => 'hello']);
        $cipher = WxMsgCrypt::encrypt(self::AES_KEY, $plain, self::APP_ID);
        $this->assertNotNull($cipher);
        $signature = WxMsgCrypt::msgSignature(self::TOKEN, '1', '', $cipher);

        $ack = $service->handlePost('', '1', '', $this->encryptedXml($cipher), 'aes', $signature);

        $envelope = simplexml_load_string($ack->body);
        $this->assertNotFalse($envelope);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}$/', (string) $envelope->Nonce);
        $reply = WxMsgCrypt::decrypt(self::AES_KEY, (string) $envelope->Encrypt, self::APP_ID);
        $this->assertNotNull($reply);
        $this->assertStringContainsString('reply', $reply);
    }

    public function test_reply_exception_is_safely_logged_and_returns_success(): void
    {
        $auto = new class () extends AutoReplyService {
            public function matchKeyword(string $keyword): ?string
            {
                throw new \RuntimeException('SQL tok SECRET-USER-TEXT <xml>');
            }
        };
        $service = $this->service([], $auto);
        $signature = WxMsgCrypt::urlSignature(self::TOKEN, '1', '2');

        $ack = $service->handlePost(
            $signature,
            '1',
            '2',
            $this->xml(['MsgType' => 'text', 'Content' => 'SECRET-USER-TEXT']),
            '',
            ''
        );

        $this->assertSame('success', $ack->body);
        $records = $this->logs->getRecords();
        $this->assertNotEmpty($records);
        $encoded = json_encode($records, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        foreach (['SECRET-USER-TEXT', self::TOKEN, 'SQL', '<xml>'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
        $this->assertSame(
            ['msg_type' => 'text', 'event' => '', 'reply_decided' => false],
            $records[array_key_last($records)]['context']
        );
    }

    /** @param array<string, string> $overrides */
    private function service(array $overrides = [], ?AutoReplyService $autoReply = null): WechatServeService
    {
        $values = array_merge([
            'wechat_official_app_id' => self::APP_ID,
            'wechat_official_token' => self::TOKEN,
            'wechat_official_aes_key' => self::AES_KEY,
            'wechat_official_encrypt_type' => '1',
        ], $overrides);
        $reader = new class ($values) implements ConfigValueReader {
            /** @param array<string, string> $values */
            public function __construct(private readonly array $values)
            {
            }

            public function getConfigValue(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }
        };

        return new WechatServeService(new WechatConfigResolver($reader), $autoReply ?? $this->autoReply());
    }

    private function autoReply(?string $keywordReply = null, ?string $subscribeReply = null): AutoReplyService
    {
        return new class ($keywordReply, $subscribeReply) extends AutoReplyService {
            /** @var list<string> */
            public array $keywords = [];
            public int $subscribeCalls = 0;

            public function __construct(
                private readonly ?string $keywordReply,
                private readonly ?string $subscribeReply,
            ) {
            }

            public function matchKeyword(string $keyword): ?string
            {
                $this->keywords[] = $keyword;

                return $this->keywordReply;
            }

            public function subscribeReply(): ?string
            {
                ++$this->subscribeCalls;

                return $this->subscribeReply;
            }
        };
    }

    /** @param array<string, string> $fields */
    private function xml(array $fields): string
    {
        $xml = '<xml><ToUserName>to-user</ToUserName><FromUserName>from-user</FromUserName>';
        foreach ($fields as $name => $value) {
            $xml .= "<{$name}><![CDATA[{$value}]]></{$name}>";
        }

        return $xml . '</xml>';
    }

    private function encryptedXml(string $cipher): string
    {
        return '<xml><Encrypt><![CDATA[' . $cipher . ']]></Encrypt></xml>';
    }
}
