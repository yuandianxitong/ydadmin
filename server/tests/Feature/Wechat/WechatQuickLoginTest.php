<?php

declare(strict_types=1);

namespace tests\Feature\Wechat;

use app\repository\user\UserRepository;
use app\service\wechat\WechatAuthService;
use core\exception\BusinessException;
use Illuminate\Database\UniqueConstraintViolationException;
use Monolog\Handler\TestHandler;
use support\Container;
use support\Db;
use support\Log;
use support\Redis;
use tests\Support\ApiTestCase;
use tests\Support\Wechat\FakeWechatHttp;
use tests\Support\Wechat\WechatUserFixtures;

/** M6a spec §4.4、§4.5：小程序快捷登录与绑手机号。 */
final class WechatQuickLoginTest extends ApiTestCase
{
    use FakeWechatHttp;
    use WechatUserFixtures;

    private TestHandler $logs;

    /** @var list<string> */
    private array $tempTokens = [];

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
            foreach ($this->tempTokens as $token) {
                Redis::del(WechatAuthService::QUICK_KEY_PREFIX . $token);
            }
            $this->tempTokens = [];
        } finally {
            $this->cleanupWechatFixtures();
            parent::tearDown();
        }
    }

    private function service(): WechatAuthService
    {
        return Container::get(WechatAuthService::class);
    }

    /** @return \GuzzleHttp\Psr7\Response */
    private static function session(string $openid, ?string $unionid = null)
    {
        return self::wechatJson(array_filter(['openid' => $openid, 'session_key' => 'SK-NOT-LEAK', 'unionid' => $unionid]));
    }

    /** @return list<\GuzzleHttp\Psr7\Response> access_token + 手机号两个应答 */
    private static function phone(string $mobile): array
    {
        return [
            self::wechatJson(['access_token' => 'ACCESS-TOKEN-X', 'expires_in' => 7200]),
            self::wechatJson(['errcode' => 0, 'errmsg' => 'ok', 'phone_info' => [
                'phoneNumber'     => $mobile,
                'purePhoneNumber' => $mobile,
                'countryCode'     => '86',
            ]]),
        ];
    }

    private function needBindphone(string $openid, ?string $unionid = null): string
    {
        $this->fakeWechatHttp([self::session($openid, $unionid)]);
        $result = $this->service()->quickLogin('code-quick', '203.0.113.7');
        $this->assertSame('need_bindphone', $result['status']);
        $this->tempTokens[] = $result['temp_token'];
        $this->restoreWechatHttp();

        return $result['temp_token'];
    }

    private function assertBusinessError(\Closure $call, string $message): void
    {
        try {
            $call();
            $this->fail('应抛出业务错误：' . $message);
        } catch (BusinessException $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    // ---------------------------------------------------------------- quickLogin

    public function test_quick_login_logs_in_an_existing_user(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $userId = $this->insertWechatUser(['mini_openid' => $openid]);
        $this->fakeWechatHttp([self::session($openid)]);

        $result = $this->service()->quickLogin('code-quick-1', '203.0.113.7');

        $this->assertSame(['status', 'token', 'user_info'], array_keys($result));
        $this->assertSame('logged_in', $result['status']);
        $this->assertSame($userId, $result['user_info']['id']);
    }

    public function test_quick_login_unknown_user_returns_need_bindphone_and_stores_one_temp_token(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $unionid = $this->fixtureOpenid('u');
        $this->fakeWechatHttp([self::session($openid, $unionid)]);

        $result = $this->service()->quickLogin('code-quick-2', '203.0.113.7');
        $this->tempTokens[] = $result['temp_token'];

        $this->assertSame(['status', 'temp_token'], array_keys($result));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $result['temp_token']);
        $key = WechatAuthService::QUICK_KEY_PREFIX . $result['temp_token'];
        $ttl = (int) Redis::ttl($key);
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(300, $ttl);
        $this->assertSame(['openid' => $openid, 'unionid' => $unionid], json_decode((string) Redis::get($key), true));
        $this->assertSame(0, Db::table('users')->where('mini_openid', $openid)->count(), '中间态不注册');
    }

    // ---------------------------------------------------------------- bindPhone

    public function test_bind_phone_registers_a_new_user_with_the_mobile(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $unionid = $this->fixtureOpenid('u');
        $mobile = $this->fixtureMobile();
        $temp = $this->needBindphone($openid, $unionid);
        $this->fakeWechatHttp(self::phone($mobile));

        $result = $this->service()->bindPhone($temp, 'phone-code-1', '203.0.113.7');

        $this->assertSame('logged_in', $result['status']);
        $this->assertSame($mobile, $result['user_info']['mobile']);
        $row = $this->userRow($result['user_info']['id']);
        $this->assertSame($openid, $row['mini_openid'] ?? null);
        $this->assertSame($unionid, $row['unionid'] ?? null);
        $this->assertSame(0, (int) Redis::exists(WechatAuthService::QUICK_KEY_PREFIX . $temp), 'temp_token 用后即删');
        $this->assertStringContainsString('wxa/business/getuserphonenumber', (string) $this->wechatRequests()[1]['request']->getUri());
    }

    public function test_bind_phone_binds_existing_mobile_user_whose_mini_openid_is_empty(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $mobile = $this->fixtureMobile();
        $userId = $this->insertWechatUser(['mobile' => $mobile]);
        $temp = $this->needBindphone($openid);
        $this->fakeWechatHttp(self::phone($mobile));

        $result = $this->service()->bindPhone($temp, 'phone-code-2', '203.0.113.7');

        $this->assertSame($userId, $result['user_info']['id']);
        $this->assertSame($openid, $this->userRow($userId)['mini_openid'] ?? null);
        $this->assertSame(1, Db::table('users')->where('mobile', $mobile)->count());
    }

    public function test_bind_phone_logs_in_when_mobile_user_already_has_this_openid(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $mobile = $this->fixtureMobile();
        $temp = $this->needBindphone($openid);
        // quickLogin 之后、bindPhone 之前，这个 openid 被绑到了该手机号用户上（例如另一台设备刚完成绑定）
        $userId = $this->insertWechatUser(['mobile' => $mobile, 'mini_openid' => $openid]);
        $this->fakeWechatHttp(self::phone($mobile));

        $result = $this->service()->bindPhone($temp, 'phone-code-3', '203.0.113.7');

        $this->assertSame($userId, $result['user_info']['id']);
    }

    public function test_bind_phone_rejects_mobile_already_bound_to_another_wechat(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $otherOpenid = $this->fixtureOpenid();
        $mobile = $this->fixtureMobile();
        $userId = $this->insertWechatUser(['mobile' => $mobile, 'mini_openid' => $otherOpenid]);
        $temp = $this->needBindphone($openid);
        $this->fakeWechatHttp(self::phone($mobile));

        $this->assertBusinessError(fn () => $this->service()->bindPhone($temp, 'phone-code-4', '203.0.113.7'), '该手机号已绑定其他微信');

        $this->assertSame($otherOpenid, $this->userRow($userId)['mini_openid'] ?? null, '原绑定不得被覆盖');
        $this->assertSame(0, Db::table('users')->where('mini_openid', $openid)->count());
    }

    public function test_bind_phone_rejects_when_this_openid_already_belongs_to_another_user(): void
    {
        $this->configureWechatApps();
        $openid = $this->fixtureOpenid();
        $mobile = $this->fixtureMobile();
        $temp = $this->needBindphone($openid);
        $ownerId = $this->insertWechatUser(['mini_openid' => $openid, 'mobile' => $this->fixtureMobile()]);
        $phoneUserId = $this->insertWechatUser(['mobile' => $mobile]);
        $this->fakeWechatHttp(self::phone($mobile));

        $this->assertBusinessError(fn () => $this->service()->bindPhone($temp, 'phone-code-5', '203.0.113.7'), '该手机号已绑定其他微信');

        $this->assertNull($this->userRow($phoneUserId)['mini_openid'] ?? null, '同一 mini_openid 不能写到两个账号上');
        $this->assertSame($openid, $this->userRow($ownerId)['mini_openid'] ?? null);
    }

    public function test_bind_phone_rejects_disabled_mobile_user(): void
    {
        $this->configureWechatApps();
        $mobile = $this->fixtureMobile();
        $userId = $this->insertWechatUser(['mobile' => $mobile, 'status' => 0]);
        $temp = $this->needBindphone($this->fixtureOpenid());
        $this->fakeWechatHttp(self::phone($mobile));

        $this->assertBusinessError(fn () => $this->service()->bindPhone($temp, 'phone-code-6', '203.0.113.7'), '账户已被禁用');
        $this->assertNull($this->userRow($userId)['mini_openid'] ?? null);
    }

    public function test_temp_token_is_single_use(): void
    {
        $this->configureWechatApps();
        $mobile = $this->fixtureMobile();
        $temp = $this->needBindphone($this->fixtureOpenid());
        $this->fakeWechatHttp(self::phone($mobile));
        $this->service()->bindPhone($temp, 'phone-code-7', '203.0.113.7');
        $this->restoreWechatHttp();
        $this->fakeWechatHttp([]);

        $this->assertBusinessError(fn () => $this->service()->bindPhone($temp, 'phone-code-7', '203.0.113.7'), '登录已过期，请重新授权');
        $this->assertSame([], $this->wechatRequests(), '凭证无效时不调用微信');
    }

    public function test_temp_token_is_consumed_even_when_phone_decryption_fails(): void
    {
        $this->configureWechatApps();
        $temp = $this->needBindphone($this->fixtureOpenid());
        $this->fakeWechatHttp([
            self::wechatJson(['access_token' => 'ACCESS-TOKEN-X', 'expires_in' => 7200]),
            self::wechatJson(['errcode' => 40029, 'errmsg' => 'invalid code']),
        ]);

        $this->assertBusinessError(fn () => $this->service()->bindPhone($temp, 'phone-code-8', '203.0.113.7'), '微信授权失败，请重试');
        $this->assertSame(0, (int) Redis::exists(WechatAuthService::QUICK_KEY_PREFIX . $temp), '一次性：失败也已消费，需重新快捷登录');
    }

    public function test_malformed_or_unknown_temp_token_is_expired_without_touching_wechat(): void
    {
        $this->configureWechatApps();
        $this->fakeWechatHttp([]);

        $this->assertBusinessError(fn () => $this->service()->bindPhone('not-a-token', 'phone-code-9', '203.0.113.7'), '登录已过期，请重新授权');
        $this->assertBusinessError(fn () => $this->service()->bindPhone(bin2hex(random_bytes(16)), 'phone-code-9', '203.0.113.7'), '登录已过期，请重新授权');
        $this->assertSame([], $this->wechatRequests());
    }

    public function test_mobile_phone_code_and_session_key_stay_out_of_logs_and_results(): void
    {
        $this->configureWechatApps();
        $mobile = $this->fixtureMobile();
        $temp = $this->needBindphone($this->fixtureOpenid());
        $this->fakeWechatHttp(self::phone($mobile));

        $result = $this->service()->bindPhone($temp, 'PHONE-CODE-SECRET', '203.0.113.7');

        $this->assertStringNotContainsString('SK-NOT-LEAK', (string) json_encode($result));
        $this->assertStringNotContainsString('ACCESS-TOKEN-X', (string) json_encode($result));
        $dump = (string) json_encode(array_map(static fn (array $r): array => [$r['message'], $r['context']], $this->logs->getRecords()), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString($mobile, $dump);
        $this->assertStringNotContainsString('PHONE-CODE-SECRET', $dump);
        $this->assertStringNotContainsString('ACCESS-TOKEN-X', $dump);
    }

    // ---------------------------------------------------------------- bindPhone：软删占位 / 唯一键竞态（fix round 1）

    /**
     * users.mobile 有 uk_mobile 唯一键但不含 deleted_at：软删会员仍占着这个键。findByAccount() 经
     * query() 的软删全局作用域看不到这一行，注册前必须额外查一次含软删行，否则唯一键冲突会在
     * register() 里变成未捕获的 QueryException（HTTP 500），还把手机号明文带进异常处理器的错误日志
     * （fix round 1 Important 1）。
     */
    public function test_bind_phone_rejects_a_mobile_held_by_a_soft_deleted_member(): void
    {
        $this->configureWechatApps();
        $mobile = $this->fixtureMobile();
        $userId = $this->insertWechatUser(['mobile' => $mobile]);
        Db::table('users')->where('id', $userId)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $temp = $this->needBindphone($this->fixtureOpenid());
        $this->fakeWechatHttp(self::phone($mobile));

        $this->assertBusinessError(fn () => $this->service()->bindPhone($temp, 'phone-code-10', '203.0.113.7'), '该手机号暂不可用，请联系客服');

        $this->assertSame(1, Db::table('users')->where('mobile', $mobile)->count(), '不应注册出新用户（Db::table 不受软删作用域影响，1 就是原来那条软删行）');
        $this->assertSame(0, Db::table('users')->where('mobile', $mobile)->whereNull('deleted_at')->count(), '没有未软删的同手机号用户');
        $dump = (string) json_encode(array_map(static fn (array $r): array => [$r['message'], $r['context']], $this->logs->getRecords()), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString($mobile, $dump, '日志不得带出手机号明文');
    }

    /**
     * 两个不同 openid 并发绑同一手机号：各自的登录锁不同（锁按 mini_openid 分），
     * mobileTakenIncludingTrashed() 的查空窗口防不住这种竞态——用仓储替身直接模拟 createWechatUser()
     * 撞上 uk_mobile 唯一键（UniqueConstraintViolationException），验证 bindPhone 把它转成同一条业务
     * 错误，且异常处理器不会记录 QueryException::getMessage()（带 SQL 绑定值＝手机号明文）。
     */
    public function test_bind_phone_converts_a_racing_unique_violation_into_a_business_error(): void
    {
        $this->configureWechatApps();
        $mobile = $this->fixtureMobile();
        $temp = $this->needBindphone($this->fixtureOpenid());
        $this->fakeWechatHttp(self::phone($mobile));

        $failing = new class ($mobile) extends UserRepository {
            public function __construct(private readonly string $bound)
            {
                parent::__construct();
            }

            public function createWechatUser(string $column, string $openid, ?string $unionid, string $nickname, ?string $avatar, ?string $mobile = null): int
            {
                $pdo = new class ("SQLSTATE[23000]: Duplicate entry '{$this->bound}' for key 'uk_mobile'") extends \PDOException {
                    /** @var string */
                    protected $code = '23000';
                };

                throw new UniqueConstraintViolationException('mysql', 'insert into `users` (`mobile`) values (?)', [$this->bound], $pdo);
            }
        };

        $original = Container::get(UserRepository::class);
        Container::set(UserRepository::class, $failing);
        Container::set(WechatAuthService::class, Container::make(WechatAuthService::class));
        try {
            $this->assertBusinessError(fn () => $this->service()->bindPhone($temp, 'phone-code-11', '203.0.113.7'), '该手机号暂不可用，请联系客服');
        } finally {
            Container::set(UserRepository::class, $original);
            Container::set(WechatAuthService::class, Container::make(WechatAuthService::class));
        }

        $this->assertSame(0, Db::table('users')->where('mobile', $mobile)->count(), '竞态失败不应留下半成品用户');
        $dump = (string) json_encode(array_map(static fn (array $r): array => [$r['message'], $r['context']], $this->logs->getRecords()), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString($mobile, $dump, '日志不得带出手机号明文（QueryException::getMessage() 会带 SQL 绑定值）');
    }
}
