<?php

declare(strict_types=1);

namespace tests\Unit\Auth;

use app\service\common\CaptchaService;
use support\Redis;
use tests\TestCase;

final class CaptchaServiceTest extends TestCase
{
    /** @var list<string> 本用例生成的验证码 key，tearDown 时删除 */
    private array $keys = [];

    protected function tearDown(): void
    {
        foreach ($this->keys as $key) {
            Redis::del('captcha.' . $key);
        }
        $this->keys = [];
        parent::tearDown();
    }

    private function generate(CaptchaService $service): string
    {
        $key = $service->generate()['key'];
        $this->keys[] = $key;

        return $key;
    }

    public function test_code_is_stored_raw_in_redis_for_300_seconds(): void
    {
        $key = $this->generate(new CaptchaService());

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $key);
        $this->assertMatchesRegularExpression('/^[a-z2-9]{4}$/', (string) Redis::get('captcha.' . $key), '存小写明文，不经 support\Cache 序列化');
        $ttl = (int) Redis::ttl('captcha.' . $key);
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(300, $ttl);
    }

    public function test_code_does_not_follow_mt_srand(): void
    {
        $service = new CaptchaService();
        try {
            mt_srand(20260911);
            $first = $this->generate($service);
            mt_srand(20260911);
            $second = $this->generate($service);
        } finally {
            mt_srand();
        }

        // mt_rand 固定种子后序列可复现；验证码字符必须来自 random_int（两次撞上同一个 4 位码的概率约 1/900 万）
        $this->assertNotSame(Redis::get('captcha.' . $first), Redis::get('captcha.' . $second));
    }

    public function test_verify_is_one_shot_and_case_insensitive(): void
    {
        $service = new CaptchaService();
        $key = $this->generate($service);
        $code = (string) Redis::get('captcha.' . $key);

        $this->assertTrue($service->verify($key, strtoupper($code)));
        $this->assertSame(0, (int) Redis::exists('captcha.' . $key), '校验后立即删除');
        $this->assertFalse($service->verify($key, $code), '同一个验证码不能再用');
    }

    public function test_a_wrong_guess_also_consumes_the_captcha(): void
    {
        $service = new CaptchaService();
        $key = $this->generate($service);
        $code = (string) Redis::get('captcha.' . $key);

        $this->assertFalse($service->verify($key, $code === 'zzzz' ? 'yyyy' : 'zzzz'));
        $this->assertFalse($service->verify($key, $code), '猜错一次即作废，不能换着码重试');
    }

    /**
     * 取值加删除必须是原子的 GETDEL，不能用 MULTI：非协程 webman 里每个 worker 只有一条 Redis 连接，
     * multi() 与 exec() 之间抛 RedisException 会把这条连接永久留在 MULTI 状态，之后这个 worker 的每条
     * 命令都会错乱。这个失败模式在进程内复现不出来（要在两条命令之间制造连接异常），只能钉住实现本身。
     */
    public function test_verify_consumes_the_code_atomically_without_multi(): void
    {
        $service = new CaptchaService();
        $source = (string) file_get_contents((string) (new \ReflectionClass($service))->getFileName());

        $this->assertStringContainsString('getDel(', $source, '一条 GETDEL 取值并删除');
        $this->assertStringNotContainsString('multi()', $source, 'MULTI 中途抛异常会把 worker 的连接永久留在事务状态');

        // 行为不变：一次性消费，且校验之后连接照常可用
        $key = $this->generate($service);
        $this->assertTrue($service->verify($key, (string) Redis::get('captcha.' . $key)));
        $this->assertSame(0, (int) Redis::exists('captcha.' . $key));
        Redis::setEx('captcha.' . $key, 5, 'probe');
        $this->assertSame('probe', Redis::get('captcha.' . $key), '校验之后连接没有被留在事务状态');
    }

    public function test_empty_or_unknown_input_is_rejected(): void
    {
        $service = new CaptchaService();

        $this->assertFalse($service->verify('', 'abcd'));
        $this->assertFalse($service->verify(bin2hex(random_bytes(16)), 'abcd'));
        $this->assertFalse($service->verify($this->generate($service), ''));
    }
}
