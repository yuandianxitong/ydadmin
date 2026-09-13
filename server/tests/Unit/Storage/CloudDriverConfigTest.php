<?php

declare(strict_types=1);

namespace tests\Unit\Storage;

use core\exception\BusinessException;
use core\storage\driver\AliyunOssDriver;
use core\storage\driver\QiniuDriver;
use core\storage\driver\TencentCosDriver;
use core\storage\StorageInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use tests\TestCase;

/**
 * 云驱动的离线断言：配置校验、URL 拼装、前置条件。
 *
 * 🔴 本文件不碰网络：三个 SDK 的客户端对象构造时都不发请求，所以能离线验证
 * 「凭据不全立刻抛业务异常、绝不回退本地」这条 spec §6.5 的红线；真正的
 * put/delete/exists 需要真实 bucket，留给运维在配置页填完凭据后手工验证。
 */
final class CloudDriverConfigTest extends TestCase
{
    /** @return array{access_key: string, access_secret: string, bucket: string, endpoint: string, region: string, domain: string} */
    private function ossConfig(array $overrides = []): array
    {
        return array_merge([
            'access_key'    => 'dummy-ak',
            'access_secret' => 'dummy-sk',
            'bucket'        => 'demo-bucket',
            'endpoint'      => 'oss-cn-hangzhou.aliyuncs.com',
            'region'        => '',
            'domain'        => '',
        ], $overrides);
    }

    /** 经反射读驱动最终解析出来的 region（构造成功即证明 SDK 拿到了它，但只有反射能断言具体值）。 */
    private function resolvedRegion(AliyunOssDriver $driver): string
    {
        return (string) (new \ReflectionProperty($driver, 'region'))->getValue($driver);
    }

    /** @return array{secret_id: string, secret_key: string, bucket: string, region: string, domain: string} */
    private function cosConfig(array $overrides = []): array
    {
        return array_merge([
            'secret_id'  => 'dummy-id',
            'secret_key' => 'dummy-key',
            'bucket'     => 'demo-1250000000',
            'region'     => 'ap-guangzhou',
            'domain'     => '',
        ], $overrides);
    }

    /** @return array{access_key: string, secret_key: string, bucket: string, domain: string} */
    private function qiniuConfig(array $overrides = []): array
    {
        return array_merge([
            'access_key' => 'dummy-ak',
            'secret_key' => 'dummy-sk',
            'bucket'     => 'demo-bucket',
            'domain'     => 'https://cdn.example.com',
        ], $overrides);
    }

    private function assertIncomplete(callable $construct, string $missingKey): void
    {
        try {
            $construct();
            $this->fail("缺少 {$missingKey} 时必须抛 BusinessException，不能构造成功");
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.storage_config_incomplete'), $e->getMessage(), $missingKey);
            $this->assertStringNotContainsString('dummy-sk', $e->getMessage(), '异常消息里不得出现凭据');
        }
    }

    public function test_aliyun_requires_ak_sk_bucket_and_endpoint(): void
    {
        foreach (['access_key', 'access_secret', 'bucket', 'endpoint'] as $key) {
            $this->assertIncomplete(fn () => new AliyunOssDriver($this->ossConfig([$key => ''])), $key);
        }
        // domain 是可选的：不填也能构造
        $this->assertInstanceOf(StorageInterface::class, new AliyunOssDriver($this->ossConfig()));
    }

    public function test_tencent_requires_id_key_bucket_and_region(): void
    {
        foreach (['secret_id', 'secret_key', 'bucket', 'region'] as $key) {
            $this->assertIncomplete(fn () => new TencentCosDriver($this->cosConfig([$key => ''])), $key);
        }
        $this->assertInstanceOf(StorageInterface::class, new TencentCosDriver($this->cosConfig()));
    }

    public function test_qiniu_requires_ak_sk_bucket_and_domain(): void
    {
        foreach (['access_key', 'secret_key', 'bucket', 'domain'] as $key) {
            $this->assertIncomplete(fn () => new QiniuDriver($this->qiniuConfig([$key => ''])), $key);
        }
        $this->assertInstanceOf(StorageInterface::class, new QiniuDriver($this->qiniuConfig()));
    }

    public function test_aliyun_url_prefers_custom_domain_then_virtual_hosted_endpoint(): void
    {
        $withDomain = new AliyunOssDriver($this->ossConfig(['domain' => 'https://cdn.example.com/', 'region' => 'cn-hangzhou']));
        $this->assertSame('https://cdn.example.com/uploads/images/a.png', $withDomain->getUrl('uploads/images/a.png'));
        $this->assertSame('https://cdn.example.com/uploads/images/a.png', $withDomain->getUrl('/uploads/images/a.png'));

        $withoutDomain = new AliyunOssDriver($this->ossConfig());
        $this->assertSame(
            'https://demo-bucket.oss-cn-hangzhou.aliyuncs.com/uploads/images/a.png',
            $withoutDomain->getUrl('uploads/images/a.png'),
            '没配自定义域名时拼公开 URL，不能用会过期的签名 URL'
        );

        $schemedEndpoint = new AliyunOssDriver($this->ossConfig(['endpoint' => 'https://oss-cn-beijing.aliyuncs.com']));
        $this->assertSame('https://demo-bucket.oss-cn-beijing.aliyuncs.com/a.png', $schemedEndpoint->getUrl('a.png'));
    }

    public function test_aliyun_explicit_region_config_wins_over_the_endpoint(): void
    {
        // 两个值故意不同：如果实现搞反了优先级，这里会拿到 cn-hangzhou
        $driver = new AliyunOssDriver($this->ossConfig([
            'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
            'region'   => '  cn-shenzhen  ',
        ]));

        $this->assertSame('cn-shenzhen', $this->resolvedRegion($driver), '显式配置优先，且首尾空白要 trim');
    }

    public function test_aliyun_falls_back_to_deriving_region_from_endpoint(): void
    {
        $this->assertSame('cn-hangzhou', $this->resolvedRegion(new AliyunOssDriver($this->ossConfig())));
        $this->assertSame(
            'cn-beijing',
            $this->resolvedRegion(new AliyunOssDriver($this->ossConfig(['endpoint' => 'https://oss-cn-beijing-internal.aliyuncs.com']))),
            'internal endpoint 也要能推导'
        );
    }

    public function test_aliyun_requires_region_when_config_empty_and_endpoint_not_derivable(): void
    {
        try {
            // 自定义域名做 endpoint：推不出 region，这正是要显式配置项的原因
            new AliyunOssDriver($this->ossConfig(['endpoint' => 'oss.example.com']));
            $this->fail('region 既没配、又推导不出来时必须明确报错');
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.storage_oss_region_required'), $e->getMessage());
        }
    }

    public function test_tencent_url_prefers_custom_domain_then_default_host(): void
    {
        $this->assertSame(
            'https://cdn.example.com/a/b.png',
            (new TencentCosDriver($this->cosConfig(['domain' => 'https://cdn.example.com/'])))->getUrl('a/b.png')
        );
        $this->assertSame(
            'https://demo-1250000000.cos.ap-guangzhou.myqcloud.com/a/b.png',
            (new TencentCosDriver($this->cosConfig()))->getUrl('a/b.png')
        );
    }

    public function test_qiniu_url_always_uses_the_configured_domain(): void
    {
        $this->assertSame(
            'https://cdn.example.com/a/b.png',
            (new QiniuDriver($this->qiniuConfig(['domain' => 'https://cdn.example.com/'])))->getUrl('/a/b.png')
        );
    }

    // ------------------------------------------------------------------
    // 七牛是手写的签名实现（Task 1 决策：不引入 qiniu/php-sdk），所以签名本身要离线钉死。
    // 下面的期望值不是从实现里抄的，是照 qiniu/php-sdk v7.14.0 的 Auth.php 算法离线算出来的。
    // ------------------------------------------------------------------

    /**
     * 上传凭证：`{ak}:{base64url(hmac_sha1(enc, sk))}:{enc}`，`enc = base64url(policyJson)`。
     *
     * 🔴 HMAC 的输入是**编码之后**的 policy（`Auth::signWithData()` 先 encode 再 sign）。
     * 签成原始 JSON 的话，真机上只表现为服务端 401——离线钉住字符串才抓得到这种错。
     */
    public function test_qiniu_upload_token_matches_the_sdk_algorithm(): void
    {
        $driver = new QiniuDriver([
            'access_key' => 'ak-test',
            'secret_key' => 'sk-test',
            'bucket'     => 'demo-bucket',
            'domain'     => 'https://cdn.example.com',
        ]);

        $token = $driver->uploadToken('uploads/files/20260913/abc.txt', 1789000000);

        $this->assertSame(
            'ak-test:qcwj3D4rC5CMsdZHsd6dx1GUhok=:eyJzY29wZSI6ImRlbW8tYnVja2V0OnVwbG9hZHMvZmlsZXMvMjAyNjA5MTMvYWJjLnR4dCIsImRlYWRsaW5lIjoxNzg5MDAwMDAwfQ==',
            $token
        );

        // 结构再拆一遍，方便将来有人改算法时一眼看出是哪一段错了
        [$ak, $signature, $encodedPolicy] = explode(':', $token, 3);
        $this->assertSame('ak-test', $ak);
        $this->assertSame(
            '{"scope":"demo-bucket:uploads/files/20260913/abc.txt","deadline":1789000000}',
            base64_decode(strtr($encodedPolicy, '-_', '+/'), true),
            'policy 里是 bucket:key 与 deadline，斜杠不转义'
        );
        $this->assertSame(
            str_replace(['+', '/'], ['-', '_'], base64_encode(hash_hmac('sha1', $encodedPolicy, 'sk-test', true))),
            $signature,
            'HMAC 的输入必须是 base64url 编码之后的 policy'
        );
        $this->assertStringEndsWith('==', $encodedPolicy, 'base64url 保留尾部填充，不 rtrim');
    }

    public function test_qiniu_default_token_deadline_is_an_hour_out(): void
    {
        $driver = new QiniuDriver($this->qiniuConfig());

        $policy = json_decode(
            (string) base64_decode(strtr(explode(':', $driver->uploadToken('a.txt'), 3)[2], '-_', '+/'), true),
            true
        );

        $this->assertIsArray($policy);
        $this->assertEqualsWithDelta(time() + 3600, $policy['deadline'], 5);
    }

    /**
     * 管理接口（stat / delete）的 `Authorization: QBox {ak}:{base64url(hmac_sha1(path."\n", sk))}`，
     * 签名数据带前导 `/`、不含 host、以 `\n` 结尾（`Auth::signRequest()`）。
     *
     * 用 Guzzle 的 MockHandler 假传输层：断言得到真实发出的方法、URL 与请求头，但一个包都不出网。
     */
    public function test_qiniu_management_requests_are_signed_with_the_qbox_header(): void
    {
        $history = [];
        $driver = $this->mockedQiniu([new Response(200, [], '{"fsize":3}'), new Response(200, [], '{}')], $history);

        $this->assertTrue($driver->delete('uploads/files/20260913/abc.txt'));
        $this->assertCount(2, $history, 'delete() 先 stat 再 delete');

        /** @var RequestInterface $stat */
        $stat = $history[0]['request'];
        $this->assertSame('GET', $stat->getMethod());
        $this->assertSame(
            'https://rs.qiniuapi.com/stat/ZGVtby1idWNrZXQ6dXBsb2Fkcy9maWxlcy8yMDI2MDkxMy9hYmMudHh0',
            (string) $stat->getUri()
        );
        $this->assertSame('QBox ak-test:Y2VvwrDaU4CLl_eq1LAx6plWPUc=', $stat->getHeaderLine('Authorization'));

        /** @var RequestInterface $delete */
        $delete = $history[1]['request'];
        $this->assertSame('POST', $delete->getMethod());
        $this->assertSame(
            'https://rs.qiniuapi.com/delete/ZGVtby1idWNrZXQ6dXBsb2Fkcy9maWxlcy8yMDI2MDkxMy9hYmMudHh0',
            (string) $delete->getUri()
        );
        $this->assertSame('QBox ak-test:BOnWIIGGYWJctawkaFQlCdZ3S50=', $delete->getHeaderLine('Authorization'));
    }

    public function test_qiniu_delete_returns_false_without_deleting_when_object_absent(): void
    {
        $history = [];
        // 七牛 stat 一个不存在的对象真实返回 HTTP 612（非标准状态码）；guzzlehttp/psr7 2.x 的
        // Response 构造函数只接受 1xx-5xx，用下面这个子类绕开该校验以复现真实响应。
        $driver = $this->mockedQiniu([new QiniuNonStandardStatusResponse(612, '{"error":"no such file or directory"}')], $history);

        $this->assertFalse($driver->delete('uploads/files/gone.txt'), '本来就不存在时返回 false，不抛');
        $this->assertCount(1, $history, '只发了 stat，不该再发 delete');
    }

    public function test_qiniu_exists_is_false_on_error_and_delete_failure_becomes_business_exception(): void
    {
        $history = [];
        $this->assertFalse($this->mockedQiniu([new Response(401, [], '{"error":"bad token"}')], $history)->exists('a.txt'));

        // stat 说在、delete 失败：这是真实的删除失败，必须抛（Task 6 靠它记 warning）
        $driver = $this->mockedQiniu([new Response(200, [], '{"fsize":3}'), new Response(401, [], '{"error":"bad token"}')], $history);
        try {
            $driver->delete('a.txt');
            $this->fail('删除失败必须抛业务异常');
        } catch (BusinessException $e) {
            $this->assertSame(
                lang('business.storage_delete_failed', ['driver' => 'qiniu', 'error' => 'HTTP 401 bad token']),
                $e->getMessage()
            );
        }
    }

    /**
     * 造一个注入了假传输层的七牛驱动。密钥对与 bucket 固定，好让上面的签名期望值可复现。
     *
     * @param list<Response>                             $responses 按顺序返回的响应
     * @param list<array{request: RequestInterface}>     $history   出参：实际发出的请求
     */
    private function mockedQiniu(array $responses, array &$history): QiniuDriver
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new QiniuDriver([
            'access_key' => 'ak-test',
            'secret_key' => 'sk-test',
            'bucket'     => 'demo-bucket',
            'domain'     => 'https://cdn.example.com',
        ], new Client(['handler' => $stack]));
    }

    public function test_put_rejects_missing_source_file_before_touching_the_network(): void
    {
        $missing = sys_get_temp_dir() . '/not-exists-' . uniqid();
        foreach ([
            new AliyunOssDriver($this->ossConfig()),
            new TencentCosDriver($this->cosConfig()),
            new QiniuDriver($this->qiniuConfig()),
        ] as $driver) {
            try {
                $driver->put($missing, 'uploads/files/x.txt');
                $this->fail(get_class($driver) . '：源文件不存在时必须在发请求之前抛出');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('源文件不存在', $e->getMessage());
            }
        }
    }
}

/**
 * guzzlehttp/psr7 2.x 的 `Response` 构造函数只接受 1xx-5xx 的状态码，而七牛 stat 一个
 * 不存在的对象真实返回非标准的 HTTP 612。这个子类绕开构造函数的范围校验，只用于离线
 * 复现该真实响应，业务代码不使用。
 */
final class QiniuNonStandardStatusResponse extends Response
{
    private readonly int $rawStatusCode;

    public function __construct(int $rawStatusCode, string $body = '')
    {
        parent::__construct(200, [], $body);
        $this->rawStatusCode = $rawStatusCode;
    }

    public function getStatusCode(): int
    {
        return $this->rawStatusCode;
    }
}
