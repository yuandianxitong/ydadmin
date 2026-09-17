<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\service\message\MessageService;
use core\exception\BusinessException;
use core\message\exception\MessageDefiniteFailure;
use core\message\exception\MessageTransientFailure;
use core\sms\SmsInterface;
use core\sms\SmsManager;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use support\Container;
use support\Db;
use support\Log;
use tests\Support\ApiTestCase;
use tests\Support\Message\FakeMessageChannels;
use tests\Support\Wechat\FakeWechatHttp;
use tests\Support\Wechat\WechatUserFixtures;

/**
 * 红线（M6b spec §2.2、§4.6、§7.3；计划 Global Constraints「日志与 message_logs 不得出现…」）：
 * message_logs 的 receiver / content / error_msg 与应用日志里，不得出现完整手机号、openid、access_token、appsecret。
 *
 * 两类来源都要钉住：
 *   1. 通道异常消息原文带接收人（假通道故意这么抛：确定失败、暂时失败、未知异常各一）——deliver 层负责遮蔽；
 *   2. 真实通道遇到网关/微信报错：短信驱动异常带手机号、微信 errmsg 回显 openid 与 access_token、
 *      Guzzle 连接异常消息带完整 URL（含 access_token）、token 失效刷新后仍失效——通道负责不带出。
 * sync 驱动下暂时失败会立即走「重试耗尽」：QueueDispatcher、ConsumerBase 都会把异常消息写进应用日志，
 * 所以这里同时抓住了「重抛消息」这条泄漏路径。
 */
final class Test33_MessageLogNoSecretLeakTest extends ApiTestCase
{
    use FakeMessageChannels;
    use FakeWechatHttp;
    use WechatUserFixtures;

    /** 按依赖顺序：通道 ← 注册表 ← 投递服务 ← 消费者；MessageService 注入注册表与队列门面 */
    private const MESSAGE_DEPENDENT_CLASSES = [
        'core\message\channel\SmsChannel',
        'core\message\channel\WechatOfficialChannel',
        'core\message\channel\WechatMiniChannel',
        'app\service\message\MessageChannelRegistry',
        'app\service\message\MessageDeliveryService',
        'app\queue\redis_slow\MessageSendConsumer',
        'app\service\message\MessageService',
    ];

    /** @var list<int> */
    private array $userIds = [];

    private bool $fakedChannels = false;

    private int $failedJobsBefore = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->failedJobsBefore = (int) Db::table('failed_jobs')->max('id');
        $this->configureWechatApps();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->fakedChannels) {
                $this->restoreMessageChannels();
            }
            $this->restoreWechatHttp();
            // 与 config/container.php 的绑定一致：凭据不全时构造即抛，SmsChannel 懒解析时自己接住
            Container::set(SmsInterface::class, \DI\factory(static fn (SmsManager $manager): SmsInterface => $manager->driver()));
            $this->rebuildRealMessageServices();
            if ($this->userIds !== []) {
                Db::table('message_logs')->whereIn('user_id', $this->userIds)->delete();
                Db::table('user_notifications')->whereIn('user_id', $this->userIds)->delete();
            }
            Db::table('failed_jobs')->where('id', '>', $this->failedJobsBefore)->where('queue', 'message-send')->delete();
            $this->cleanupWechatFixtures();
        } finally {
            $this->fakedChannels = false;
            $this->userIds = [];
            parent::tearDown();
        }
    }

    public function test_channel_exceptions_quoting_receivers_are_not_persisted_or_logged(): void
    {
        $user = $this->createReceiver();
        $code = $this->createTemplate(['sms', 'wechat_official', 'wechat_mini']);
        $this->fakeMessageChannels();
        $this->fakedChannels = true;
        $this->failNextSend('sms', new MessageDefiniteFailure("gateway rejected {$user['mobile']}"));
        $this->failNextSend('wechat_official', new MessageTransientFailure("timeout sending to {$user['oa']}"));
        $this->failNextSend('wechat_mini', new \RuntimeException("unexpected reply for {$user['mini']}"));

        $logs = $this->sendCapturingLogs($user['id'], $code);

        $rows = $this->messageLogs($user['id']);
        $channels = array_column($rows, 'channel');
        sort($channels);
        $this->assertSame(['sms', 'wechat_mini', 'wechat_official'], $channels, '三个外发通道各一条日志');
        foreach ($rows as $row) {
            $this->assertSame(2, (int) $row['status'], "{$row['channel']}：确定失败直接置 2；暂时失败与未知异常在 sync 驱动下即重试耗尽置 2");
        }
        $this->assertNoSecrets([$user['mobile'], $user['oa'], $user['mini']], $rows, $logs);
    }

    /** @return array<string, array{string}> */
    public static function realChannelFailures(): array
    {
        return [
            '短信驱动异常原文带手机号'                 => ['sms'],
            '公众号 43004，errmsg 回显 openid 与 token' => ['official_43004'],
            '公众号 -1 系统繁忙（暂时失败）'            => ['official_busy'],
            '公众号连接失败，Guzzle 消息带完整 URL'     => ['official_connect'],
            '小程序 43101，errmsg 回显 openid 与 token' => ['mini_43101'],
            '小程序 token 失效，刷新后仍失效'           => ['mini_token_invalid'],
        ];
    }

    #[DataProvider('realChannelFailures')]
    public function test_real_channel_failures_do_not_leak_receivers_or_tokens(string $case): void
    {
        $user = $this->createReceiver();
        $token = 'AT33A' . bin2hex(random_bytes(16));
        $token2 = 'AT33B' . bin2hex(random_bytes(16));
        $tokenReply = static fn (string $t): \GuzzleHttp\Psr7\Response => self::wechatJson(['access_token' => $t, 'expires_in' => 7200]);
        $templateSend = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $token;

        [$channel, $responses] = match ($case) {
            'sms'            => ['sms', null],
            'official_43004' => ['wechat_official', [
                $tokenReply($token),
                self::wechatJson(['errcode' => 43004, 'errmsg' => "require subscribe hint: [touser={$user['oa']}] access_token={$token}"]),
            ]],
            'official_busy' => ['wechat_official', [
                $tokenReply($token),
                self::wechatJson(['errcode' => -1, 'errmsg' => "system error access_token={$token} touser={$user['oa']}"]),
            ]],
            'official_connect' => ['wechat_official', [
                $tokenReply($token),
                new ConnectException("cURL error 28: Operation timed out for {$templateSend}&touser={$user['oa']}", new PsrRequest('POST', $templateSend)),
            ]],
            'mini_43101' => ['wechat_mini', [
                $tokenReply($token),
                self::wechatJson(['errcode' => 43101, 'errmsg' => "user refuse to accept the msg rid: {$user['mini']} access_token={$token}"]),
            ]],
            'mini_token_invalid' => ['wechat_mini', [
                $tokenReply($token),
                self::wechatJson(['errcode' => 40001, 'errmsg' => "invalid credential, access_token is invalid or not latest: {$token}"]),
                $tokenReply($token2),
                self::wechatJson(['errcode' => 40001, 'errmsg' => "invalid credential, access_token is invalid or not latest: {$token2}"]),
            ]],
            default => throw new \LogicException($case),
        };

        $smsCallCount = null;
        if ($responses === null) {
            // 非静态计数器：靠构造注入捕获，证明假通道确实被调到，而不只是从未执行就通过断言
            $smsCallCount = new class () {
                public int $calls = 0;
            };
            Container::set(SmsInterface::class, new class ($user['mobile'], $smsCallCount) implements SmsInterface {
                public function __construct(private readonly string $mobile, private readonly object $callCount)
                {
                }

                public function send(string $mobile, string $templateId, array $vars): void
                {
                    $this->callCount->calls++;
                    throw new BusinessException("短信网关拒绝：手机号 {$this->mobile} 与签名不匹配");
                }
            });
        } else {
            $this->fakeWechatHttp($responses);
        }
        $this->rebuildRealMessageServices();
        $code = $this->createTemplate([$channel]);

        $logs = $this->sendCapturingLogs($user['id'], $code);

        $rows = $this->messageLogs($user['id']);
        $this->assertCount(1, $rows);
        $this->assertSame($channel, $rows[0]['channel']);
        $this->assertSame(2, (int) $rows[0]['status'], '失败必须如实落库');
        $this->assertNotSame('', (string) $rows[0]['error_msg'], '失败必须留下可排查的 error_msg');
        if ($responses !== null) {
            $this->assertCount(count($responses), $this->wechatRequests(), '通道必须真的调到（假）微信，否则本用例什么也没验证');
        } else {
            $this->assertSame(1, $smsCallCount->calls, '假 SmsInterface 必须被真正调用一次，否则本用例什么也没验证泄漏路径');
        }

        $secrets = [$user['mobile'], $user['oa'], $user['mini'], $token, $token2];
        foreach (Db::table('system_configs')->whereIn('config_key', ['wechat_official_app_secret', 'wechat_mini_app_secret'])->pluck('config_value') as $secret) {
            $secrets[] = (string) $secret;
        }
        $this->assertNoSecrets($secrets, $rows, $logs);
    }

    /** @return array{id: int, mobile: string, oa: string, mini: string} */
    private function createReceiver(): array
    {
        $mobile = $this->fixtureMobile();
        $oa = $this->fixtureOpenid('oOA');
        $mini = $this->fixtureOpenid('oMINI');
        $id = $this->insertWechatUser(['mobile' => $mobile, 'oa_openid' => $oa, 'mini_openid' => $mini, 'nickname' => '红线三十三']);
        $this->userIds[] = $id;

        return ['id' => $id, 'mobile' => $mobile, 'oa' => $oa, 'mini' => $mini];
    }

    /** @param list<string> $channels 启用的外发通道；站内信一律停用 */
    private function createTemplate(array $channels): string
    {
        $code = 't33_' . bin2hex(random_bytes(4));
        $mapping = json_encode(['thing1' => '${nickname}'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('message_templates')->insertGetId([
            'name'                        => '红线33',
            'code'                        => $code,
            'status'                      => 1,
            'sms_enabled'                 => (int) in_array('sms', $channels, true),
            'sms_template_id'             => 'SMS_T33',
            'sms_content'                 => '',
            'wechat_official_enabled'     => (int) in_array('wechat_official', $channels, true),
            'wechat_official_template_id' => 'TPL_OA_T33',
            'wechat_official_url'         => '',
            'wechat_official_data'        => $mapping,
            'wechat_mini_enabled'         => (int) in_array('wechat_mini', $channels, true),
            'wechat_mini_template_id'     => 'TPL_MINI_T33',
            'wechat_mini_page'            => '',
            'wechat_mini_data'            => $mapping,
            'site_enabled'                => 0,
            'site_title'                  => '',
            'site_content'                => '',
            'variables'                   => json_encode([['key' => 'nickname', 'name' => '昵称', 'example' => '张三']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at'                  => $now,
            'updated_at'                  => $now,
        ]);
        $this->track('message_templates', $id);

        return $code;
    }

    private function sendCapturingLogs(int $userId, string $code): TestHandler
    {
        $logs = new TestHandler();
        Log::channel()->pushHandler($logs);
        try {
            Container::get(MessageService::class)->sendToUser($userId, $code, ['nickname' => '红线三十三']);
        } finally {
            Log::channel()->popHandler();
        }

        return $logs;
    }

    /** @return list<array<string, mixed>> */
    private function messageLogs(int $userId): array
    {
        return Db::table('message_logs')->where('user_id', $userId)->orderBy('id')->get()
            ->map(static fn (object $row): array => (array) $row)->values()->all();
    }

    /**
     * @param list<string> $secrets
     * @param list<array<string, mixed>> $rows
     */
    private function assertNoSecrets(array $secrets, array $rows, TestHandler $logs): void
    {
        $secrets = array_values(array_filter($secrets, static fn (string $s): bool => $s !== ''));
        foreach ($rows as $row) {
            foreach (['receiver', 'content', 'error_msg'] as $column) {
                foreach ($secrets as $secret) {
                    $this->assertStringNotContainsString($secret, (string) ($row[$column] ?? ''), "message_logs.{$column}（{$row['channel']}）不得含完整手机号、openid、access_token、appsecret");
                }
            }
        }

        foreach ($logs->getRecords() as $record) {
            $text = $record['message'] . ' ' . $this->flatten($record['context']);
            foreach ($secrets as $secret) {
                $this->assertStringNotContainsString($secret, $text, "应用日志不得含完整手机号、openid、access_token、appsecret：{$record['message']}");
            }
        }
    }

    /** 上下文里的异常对象 json_encode 后是 {}，会漏掉它的消息：逐项展开，异常取类名与消息 */
    private function flatten(mixed $value): string
    {
        if ($value instanceof \Throwable) {
            return $value::class . ' ' . $value->getMessage() . ' ' . $this->flatten($value->getPrevious());
        }
        if (is_array($value)) {
            return implode(' ', array_map(fn (mixed $v): string => $this->flatten($v), $value));
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return is_object($value) ? (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : '';
    }

    /** #[Inject] 在首次解析时定死依赖：换了 WechatHttpClient / SmsInterface / 假通道之后按依赖顺序重建 */
    private function rebuildRealMessageServices(): void
    {
        foreach (self::MESSAGE_DEPENDENT_CLASSES as $class) {
            if (class_exists($class)) {
                Container::set($class, Container::make($class, []));
            }
        }
    }
}
