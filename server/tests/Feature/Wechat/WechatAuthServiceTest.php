<?php

declare(strict_types=1);

namespace tests\Feature\Wechat;

use app\service\wechat\WechatAuthService;
use core\exception\BusinessException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Monolog\Handler\TestHandler;
use support\Container;
use support\Db;
use support\Log;
use support\Redis;
use tests\Support\ApiTestCase;
use tests\Support\Wechat\FakeWechatHttp;
use tests\Support\Wechat\WechatUserFixtures;

/** M6a spec §4.1–4.3、§4.10：小程序静默登录与 PC 扫码登录。 */
final class WechatAuthServiceTest extends ApiTestCase
{
    use FakeWechatHttp;
    use WechatUserFixtures;

    private TestHandler $logs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logs = new TestHandler();
        Log::channel()->pushHandler($this->logs);
    }

    protected function tearDown(): void
    {
        try {
            Log::channel()->popHandler();
            $this->restoreWechatHttp();
        } finally {
            $this->cleanupWechatFixtures();
            parent::tearDown();
        }
    }

    private function service(): WechatAuthService
    {
        return Container::get(WechatAuthService::class);
    }

    /** @return array<string, mixed> */
    private function sessionBody(string $openid, ?string $unionid = null): array
    {
        return array_filter([
            'openid'      => $openid,
            'session_key' => 'SESSION-KEY-MUST-NOT-LEAK',
            'unionid'     => $unionid,
        ], static fn ($v): bool => $v !== null);
    }

    private function assertBusinessError(\Closure $call, string $message, int $code = 400): void
    {
        try {
            $call();
            $this->fail('应抛出业务错误：' . $message);
        } catch (BusinessException $e) {
            $this->assertSame($message, $e->getMessage());
            $this->assertSame($code, $e->getCode());
        }
    }

    // ---------------------------------------------------------------- miniLogin

    public function test_mini_login_registers_new_user_and_issues_token(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $unionid = $this->fixtureOpenid('u');
        $this->fakeWechatHttp([self::wechatJson($this->sessionBody($openid, $unionid))]);

        $result = $this->service()->miniLogin('code-mini-1', '203.0.113.5');

        $this->assertNotSame('', $result['token']);
        $this->assertSame('微信用户', $result['user_info']['nickname']);
        $this->assertNull($result['user_info']['mobile']);
        $row = $this->userRow($result['user_info']['id']);
        $this->assertNotNull($row);
        $this->assertSame($openid, $row['mini_openid']);
        $this->assertSame($unionid, $row['unionid']);
        $this->assertSame(1, (int) $row['status']);

        $requests = $this->wechatRequests();
        $this->assertCount(1, $requests);
        $this->assertStringContainsString('sns/jscode2session', (string) $requests[0]['request']->getUri());
    }

    public function test_mini_login_matches_existing_mini_openid_without_registering(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $userId = $this->insertWechatUser(['mini_openid' => $openid]);
        $this->fakeWechatHttp([self::wechatJson($this->sessionBody($openid))]);

        $result = $this->service()->miniLogin('code-mini-2', '203.0.113.5');

        $this->assertSame($userId, $result['user_info']['id']);
        $this->assertSame(1, Db::table('users')->where('mini_openid', $openid)->count());
    }

    public function test_unionid_match_binds_the_empty_column_of_that_user(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $unionid = $this->fixtureOpenid('u');
        $userId = $this->insertWechatUser(['unionid' => $unionid, 'openid' => $this->fixtureOpenid()]);
        $this->fakeWechatHttp([self::wechatJson($this->sessionBody($openid, $unionid))]);

        $result = $this->service()->miniLogin('code-mini-3', '203.0.113.5');

        $this->assertSame($userId, $result['user_info']['id']);
        $this->assertSame($openid, $this->userRow($userId)['mini_openid'] ?? null);
    }

    public function test_unionid_match_never_overwrites_a_different_openid(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $otherOpenid = $this->fixtureOpenid();
        $unionid = $this->fixtureOpenid('u');
        $existingId = $this->insertWechatUser(['unionid' => $unionid, 'mini_openid' => $otherOpenid]);
        $this->fakeWechatHttp([self::wechatJson($this->sessionBody($openid, $unionid))]);

        $result = $this->service()->miniLogin('code-mini-4', '203.0.113.5');

        $this->assertNotSame($existingId, $result['user_info']['id'], '已绑定其他 openid 的账号不能被接管');
        $this->assertSame($otherOpenid, $this->userRow($existingId)['mini_openid'] ?? null, '原绑定不得被覆盖');
        $newRow = $this->userRow($result['user_info']['id']);
        $this->assertSame($openid, $newRow['mini_openid'] ?? null);
        $this->assertNull($newRow['unionid'] ?? null, 'unionid 已被其他账号持有时新账号不写 unionid');
    }

    public function test_disabled_user_is_rejected(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $this->insertWechatUser(['mini_openid' => $openid, 'status' => 0]);
        $this->fakeWechatHttp([self::wechatJson($this->sessionBody($openid))]);

        $this->assertBusinessError(fn () => $this->service()->miniLogin('code-mini-5', '203.0.113.5'), '账户已被禁用');
    }

    public function test_not_configured_fails_before_calling_wechat(): void
    {
        $this->setConfig('wechat_mini_app_id', '');
        $this->fakeWechatHttp([]);

        $this->assertBusinessError(fn () => $this->service()->miniLogin('code-mini-6', '203.0.113.5'), '微信登录未配置');
        $this->assertSame([], $this->wechatRequests());
    }

    public function test_rejected_code_maps_to_auth_failed_and_logs_errcode_without_secrets(): void
    {
        $this->configureWechatApps();
        $this->fakeWechatHttp([self::wechatJson(['errcode' => 40029, 'errmsg' => 'invalid code'])]);

        $this->assertBusinessError(fn () => $this->service()->miniLogin('CODE-SECRET-40029', '203.0.113.5'), '微信授权失败，请重试');

        $this->assertTrue($this->logs->hasWarningRecords());
        $dump = json_encode(array_map(static fn (array $r): array => [$r['message'], $r['context']], $this->logs->getRecords()), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('40029', (string) $dump);
        $this->assertStringNotContainsString('CODE-SECRET-40029', (string) $dump);
        $this->assertStringNotContainsString('secret-', (string) $dump);
    }

    public function test_network_failure_maps_to_unavailable(): void
    {
        $this->configureWechatApps();
        $this->fakeWechatHttp([new ConnectException('timed out', new PsrRequest('GET', 'https://api.weixin.qq.com/sns/jscode2session'))]);

        $this->assertBusinessError(fn () => $this->service()->miniLogin('code-mini-7', '203.0.113.5'), '微信服务暂不可用，请稍后重试');
    }

    public function test_held_login_lock_yields_429_and_registers_nobody(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $lockKey = "wechat:login_lock:mini_openid:{$openid}";
        Redis::set($lockKey, 'someone-else', 'EX', 30);
        $this->fakeWechatHttp([self::wechatJson($this->sessionBody($openid))]);

        $this->assertBusinessError(fn () => $this->service()->miniLogin('code-mini-8', '203.0.113.5'), '操作过于频繁，请稍后重试', 429);

        $this->assertSame(0, Db::table('users')->where('mini_openid', $openid)->count());
        $this->assertSame('someone-else', Redis::get($lockKey), '不得释放别人持有的锁');
    }

    public function test_login_lock_is_released_after_success_and_after_failure(): void
    {
        $this->configureWechatApps();
        $okOpenid = $this->fixtureOpenid();
        $disabledOpenid = $this->fixtureOpenid();
        $this->insertWechatUser(['mini_openid' => $disabledOpenid, 'status' => 0]);
        $this->fakeWechatHttp([
            self::wechatJson($this->sessionBody($okOpenid)),
            self::wechatJson($this->sessionBody($disabledOpenid)),
        ]);

        $this->service()->miniLogin('code-mini-9', '203.0.113.5');
        $this->assertBusinessError(fn () => $this->service()->miniLogin('code-mini-10', '203.0.113.5'), '账户已被禁用');

        $this->assertSame(0, (int) Redis::exists("wechat:login_lock:mini_openid:{$okOpenid}"));
        $this->assertSame(0, (int) Redis::exists("wechat:login_lock:mini_openid:{$disabledOpenid}"));
    }

    public function test_login_result_never_contains_session_key(): void
    {
        $this->configureWechatApps();
        $this->fakeWechatHttp([self::wechatJson($this->sessionBody($this->fixtureOpenid(), $this->fixtureOpenid('u')))]);

        $result = $this->service()->miniLogin('code-mini-11', '203.0.113.5');

        $encoded = (string) json_encode($result);
        $this->assertStringNotContainsString('SESSION-KEY-MUST-NOT-LEAK', $encoded);
        $this->assertStringNotContainsString('session_key', $encoded);
        $this->assertSame(['token', 'user_info'], array_keys($result));
        $this->assertSame(['id', 'nickname', 'avatar', 'mobile'], array_keys($result['user_info']));
    }

    // ---------------------------------------------------------------- webLogin

    public function test_web_login_registers_with_userinfo_profile(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $unionid = $this->fixtureOpenid('u');
        $this->fakeWechatHttp([
            self::wechatJson(['access_token' => 'OAUTH-AT', 'expires_in' => 7200, 'openid' => $openid, 'unionid' => $unionid, 'scope' => 'snsapi_login']),
            self::wechatJson(['openid' => $openid, 'nickname' => '张三', 'headimgurl' => 'https://thirdwx.qlogo.cn/a.png']),
        ]);

        $result = $this->service()->webLogin('code-web-1', '203.0.113.6');

        $this->assertSame('张三', $result['user_info']['nickname']);
        $this->assertSame('https://thirdwx.qlogo.cn/a.png', $result['user_info']['avatar']);
        $row = $this->userRow($result['user_info']['id']);
        $this->assertSame($openid, $row['openid'] ?? null);
        $this->assertNull($row['mini_openid'] ?? null);
        $this->assertSame($unionid, $row['unionid'] ?? null);
        $this->assertStringContainsString('sns/oauth2/access_token', (string) $this->wechatRequests()[0]['request']->getUri());
        $this->assertStringContainsString('sns/userinfo', (string) $this->wechatRequests()[1]['request']->getUri());
    }

    public function test_web_login_userinfo_failure_falls_back_to_default_nickname(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $this->fakeWechatHttp([
            self::wechatJson(['access_token' => 'OAUTH-AT', 'expires_in' => 7200, 'openid' => $openid]),
            self::wechatJson(['errcode' => 40003, 'errmsg' => 'invalid openid']),
        ]);

        $result = $this->service()->webLogin('code-web-2', '203.0.113.6');

        $this->assertSame('微信用户', $result['user_info']['nickname']);
        $this->assertNull($result['user_info']['avatar']);
        $this->assertSame($openid, $this->userRow($result['user_info']['id'])['openid'] ?? null);
    }

    public function test_web_login_existing_user_does_not_fetch_userinfo(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $userId = $this->insertWechatUser(['openid' => $openid]);
        $this->fakeWechatHttp([
            self::wechatJson(['access_token' => 'OAUTH-AT', 'expires_in' => 7200, 'openid' => $openid]),
        ]);

        $result = $this->service()->webLogin('code-web-3', '203.0.113.6');

        $this->assertSame($userId, $result['user_info']['id']);
        $this->assertCount(1, $this->wechatRequests(), '已有账号不再调用 sns/userinfo');
    }

    public function test_web_login_uses_open_platform_credentials_not_mini(): void
    {
        $this->configureWechatApps();
        $this->setConfig('wechat_open_app_id', '');
        $this->fakeWechatHttp([]);

        $this->assertBusinessError(fn () => $this->service()->webLogin('code-web-4', '203.0.113.6'), '微信登录未配置');
        $this->assertSame([], $this->wechatRequests());
    }
}
