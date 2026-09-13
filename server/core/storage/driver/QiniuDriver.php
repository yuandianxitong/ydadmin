<?php

declare(strict_types=1);

namespace core\storage\driver;

use core\exception\BusinessException;
use core\storage\StorageInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * 七牛云驱动。**不依赖 `qiniu/php-sdk`**——该包最新 tag（v7.14.0）在 PHP 8.4 下有隐式可空参数
 * 废弃告警，其中一条经 composer 的 `files` 自动加载在 `vendor/autoload.php` 阶段就触发，等于每个
 * PHP 进程启动都带一条告警，还会顶穿 `phpunit.xml` 的 `failOnDeprecation`（Task 1 实测结论）。
 * 七牛这三个接口的签名很短，自己用 Guzzle 发比拖一个不合规的依赖便宜。
 *
 * 签名算法逐行对照 `qiniu/php-sdk v7.14.0` 的 `Auth.php` / `functions.php` 抄出来：
 *   - `base64url()` 保留尾部 `=` 填充（`\Qiniu\base64_urlSafeEncode()` 只替换 `+/`，不 rtrim）；
 *   - 上传凭证 `{ak}:{base64url(hmac_sha1(enc, sk))}:{enc}`，其中 `enc = base64url(policyJson)`。
 *     🔴 HMAC 的输入是**编码之后**的 policy（`Auth::signWithData()` 先 encode 再 sign），
 *     签成原始 JSON 会被服务端 401，而且本地断言不出来；
 *   - 管理接口 `Authorization: QBox {ak}:{base64url(hmac_sha1(path . "\n", sk))}`，`path` 带前导
 *     `/`、不含 host（`Auth::signRequest()`）。
 *
 * `domain` 是必填：七牛没有「默认公开域名」，缺了它 `getUrl()` 拼不出任何能访问的地址。
 */
final class QiniuDriver implements StorageInterface
{
    /** 上传入口（华东以外的区域也接受这个域名，七牛会自行路由） */
    private const UPLOAD_HOST = 'https://up.qiniup.com';

    /** 资源管理入口（stat / delete） */
    private const RS_HOST = 'https://rs.qiniuapi.com';

    /** 上传凭证有效期（秒），与 SDK 默认值一致 */
    private const TOKEN_TTL = 3600;

    private readonly string $accessKey;

    private readonly string $secretKey;

    private readonly string $bucket;

    /** 访问域名（含协议），已去掉尾部 `/` */
    private readonly string $domain;

    private readonly ClientInterface $http;

    /**
     * @param array{access_key: string, secret_key: string, bucket: string, domain: string} $config
     * @param ClientInterface|null $httpClient 仅供测试注入假传输层；业务代码不要传
     */
    public function __construct(array $config, ?ClientInterface $httpClient = null)
    {
        foreach (['access_key', 'secret_key', 'bucket', 'domain'] as $required) {
            if (($config[$required] ?? '') === '') {
                throw new BusinessException(lang('business.storage_config_incomplete'));
            }
        }

        $this->accessKey = $config['access_key'];
        $this->secretKey = $config['secret_key'];
        $this->bucket = $config['bucket'];
        $this->domain = rtrim($config['domain'], '/');
        $this->http = $httpClient ?? new Client(['timeout' => 30]);
    }

    public function put(string $localTmpPath, string $targetRelativePath): void
    {
        if (!is_file($localTmpPath)) {
            throw new RuntimeException("源文件不存在: {$localTmpPath}");
        }

        $key = self::normalize($targetRelativePath);
        $handle = fopen($localTmpPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("源文件无法读取: {$localTmpPath}");
        }

        try {
            $response = $this->http->request('POST', self::UPLOAD_HOST, [
                'multipart' => [
                    ['name' => 'token', 'contents' => $this->uploadToken($key)],
                    ['name' => 'key', 'contents' => $key],
                    ['name' => 'file', 'contents' => $handle, 'filename' => basename($key)],
                ],
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            throw new BusinessException(lang('business.storage_upload_failed', ['driver' => 'qiniu', 'error' => $e->getMessage()]));
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        $this->assertSuccessful($response, 'business.storage_upload_failed');

        @unlink($localTmpPath);
    }

    public function getUrl(string $relativePath): string
    {
        return $this->domain . '/' . self::normalize($relativePath);
    }

    public function delete(string $relativePath): bool
    {
        $key = self::normalize($relativePath);
        // 七牛删不存在的 key 会返回 612，这里先查一次，把「本来就没有」与「删失败」分开
        if (!$this->exists($key)) {
            return false;
        }

        $path = '/delete/' . self::entry($this->bucket, $key);
        try {
            $response = $this->http->request('POST', self::RS_HOST . $path, [
                'headers'     => ['Authorization' => $this->authorization($path)],
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            throw new BusinessException(lang('business.storage_delete_failed', ['driver' => 'qiniu', 'error' => $e->getMessage()]));
        }

        $this->assertSuccessful($response, 'business.storage_delete_failed');

        return true;
    }

    public function exists(string $relativePath): bool
    {
        $path = '/stat/' . self::entry($this->bucket, self::normalize($relativePath));
        try {
            $response = $this->http->request('GET', self::RS_HOST . $path, [
                'headers'     => ['Authorization' => $this->authorization($path)],
                'http_errors' => false,
            ]);
        } catch (\Throwable) {
            return false; // 接口约定：存在性判断失败一律当作「不存在」，不抛
        }

        return $response->getStatusCode() === 200;
    }

    /**
     * 上传凭证（`Auth::uploadToken()` 的等价实现）。public 是为了让测试能对固定密钥对与固定
     * deadline 把字符串钉死——签名算法错了在真机上只表现为 401，离线能断言才抓得住。
     *
     * @param int|null $deadline 过期时间戳；默认 `time() + 3600`，测试传固定值
     */
    public function uploadToken(string $key, ?int $deadline = null): string
    {
        $policy = (string) json_encode([
            'scope'    => $this->bucket . ':' . self::normalize($key),
            'deadline' => $deadline ?? (time() + self::TOKEN_TTL),
        ], JSON_UNESCAPED_SLASHES);

        $encoded = self::base64url($policy);

        // 🔴 签的是 $encoded，不是 $policy（Auth::signWithData()）
        return $this->sign($encoded) . ':' . $encoded;
    }

    /** 管理接口的 Authorization 头（`Auth::signRequest()` + "QBox " 前缀）。 */
    private function authorization(string $path): string
    {
        return 'QBox ' . $this->sign($path . "\n");
    }

    /** `Auth::sign()`：`{ak}:{base64url(hmac_sha1($data, $sk))}`。 */
    private function sign(string $data): string
    {
        return $this->accessKey . ':' . self::base64url(hash_hmac('sha1', $data, $this->secretKey, true));
    }

    /** 非 2xx 一律转成业务异常，错误文本取七牛返回体里的 `error` 字段。 */
    private function assertSuccessful(ResponseInterface $response, string $langKey): void
    {
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return;
        }

        throw new BusinessException(lang($langKey, [
            'driver' => 'qiniu',
            'error'  => "HTTP {$status} " . self::reasonOf($response),
        ]));
    }

    private static function reasonOf(ResponseInterface $response): string
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        if (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])) {
            return $decoded['error'];
        }

        return mb_substr($body, 0, 200);
    }

    /** `\Qiniu\entry()`：`base64url("{bucket}:{key}")`。 */
    private static function entry(string $bucket, string $key): string
    {
        return self::base64url($bucket . ':' . $key);
    }

    /** `\Qiniu\base64_urlSafeEncode()`：只替换 `+/`，**保留** `=` 填充。 */
    private static function base64url(string $data): string
    {
        return str_replace(['+', '/'], ['-', '_'], base64_encode($data));
    }

    private static function normalize(string $relativePath): string
    {
        return ltrim(str_replace('\\', '/', $relativePath), '/');
    }
}
