<?php

declare(strict_types=1);

namespace core\storage\driver;

use AlibabaCloud\Oss\V2\Client;
use AlibabaCloud\Oss\V2\Config;
use AlibabaCloud\Oss\V2\Credentials\StaticCredentialsProvider;
use AlibabaCloud\Oss\V2\Models\DeleteObjectRequest;
use AlibabaCloud\Oss\V2\Models\PutObjectRequest;
use core\exception\BusinessException;
use core\storage\StorageInterface;
use GuzzleHttp\HandlerStack;
use RuntimeException;

/**
 * 阿里云 OSS 驱动（SDK：alibabacloud/oss-v2，命名空间 AlibabaCloud\Oss\V2）。
 *
 * 🔴 与 TP8 的差异（两处都是修复，不是口味）：
 *   1. TP8 import 的是 `OSS\OssClient`（属于 aliyuncs/oss-sdk-php），而 composer 里装的一直是
 *      alibabacloud/oss-v2——那段代码只要真跑到就是 "Class not found" fatal。这里按 v2 的
 *      Config + Client + Models\*Request 重写。
 *   2. 没配自定义域名时，TP8 回落到 `signUrl(..., 3600)`，而这个带签名的 URL 会被写进
 *      files.url 持久化：一小时后库里所有链接 403。这里改为拼虚拟主机风格的公开 URL。
 *
 * region：v2 的 V4 签名必须有 region。取值顺序是「显式配置 `storage_oss_region` → 从 endpoint 推导
 * → 都没有就明确报错」（控制者 2026-09-13 裁定）：推导在运维把 endpoint 填成自定义域名时必然失效，
 * 而阿里云控制台上地域是明写的，所以以显式配置为准、推导只当兜底，绝不把问题留给 SDK 的签名阶段。
 */
final class AliyunOssDriver implements StorageInterface
{
    private readonly Client $client;

    private readonly string $bucket;

    /** 自定义访问域名，已去掉尾部 `/`；为空表示用默认公开 URL */
    private readonly string $domain;

    /** endpoint 的主机名（已去掉协议与尾部 `/`），如 oss-cn-hangzhou.aliyuncs.com */
    private readonly string $endpointHost;

    /** 最终生效的地域，如 cn-hangzhou（显式配置优先，其次从 endpoint 推导） */
    private readonly string $region;

    /**
     * @param array{access_key: string, access_secret: string, bucket: string, endpoint: string, region: string, domain: string} $config
     * @param HandlerStack|null $handlerStack 仅供测试注入假传输层（配 MockHandler）；业务代码不要传
     */
    public function __construct(array $config, ?HandlerStack $handlerStack = null)
    {
        foreach (['access_key', 'access_secret', 'bucket', 'endpoint'] as $required) {
            // trim 之后再判断：管理端是文本输入框，纯空格必须当「没填」处理
            if (trim((string) ($config[$required] ?? '')) === '') {
                throw new BusinessException(lang('business.storage_config_incomplete'));
            }
        }

        $this->bucket = $config['bucket'];
        $this->domain = rtrim($config['domain'], '/');
        $this->endpointHost = self::hostOf($config['endpoint']);
        $this->region = self::resolveRegion($config['region'], $this->endpointHost);

        $cfg = Config::loadDefault();
        $cfg->setCredentialsProvider(new StaticCredentialsProvider($config['access_key'], $config['access_secret']));
        $cfg->setEndpoint($this->endpointHost);
        $cfg->setRegion($this->region);

        $this->client = new Client($cfg, $handlerStack !== null ? ['handler' => $handlerStack] : []);
    }

    public function put(string $localTmpPath, string $targetRelativePath): void
    {
        if (!is_file($localTmpPath)) {
            throw new RuntimeException("源文件不存在: {$localTmpPath}");
        }

        $key = self::normalize($targetRelativePath);
        try {
            $this->client->putObjectFromFile(new PutObjectRequest($this->bucket, $key), $localTmpPath);
        } catch (\Throwable $e) {
            throw new BusinessException(lang('business.storage_upload_failed', ['driver' => 'aliyun', 'error' => $e->getMessage()]));
        }

        // 与 LocalDriver::put()（rename 走人）语义一致：落盘成功后源临时文件不再存在
        @unlink($localTmpPath);
    }

    public function getUrl(string $relativePath): string
    {
        $key = self::normalize($relativePath);
        if ($this->domain !== '') {
            return $this->domain . '/' . $key;
        }

        return "https://{$this->bucket}.{$this->endpointHost}/{$key}";
    }

    /**
     * 🔴 delete() 不能借道 exists() 的布尔返回值——`exists()` 把「真的不存在」与
     * 「查不了（凭据错/权限不足/网络问题）」压成了同一个 false，密钥配错时 delete() 就会
     * 表现成「悄悄删了个不存在的文件」而不是报错。这里直接调用 `isObjectExist()` 并且不吞
     * 异常：该方法本身已经把 `NoSuchKey`/404 这个「真的不存在」信号转成 `false` 返回，
     * 其余任何异常（鉴权失败、网络故障等）都会原样冒出来，在这里转成 `storage_delete_failed`。
     */
    public function delete(string $relativePath): bool
    {
        $key = self::normalize($relativePath);
        try {
            $exists = $this->client->isObjectExist($this->bucket, $key);
        } catch (\Throwable $e) {
            throw new BusinessException(lang('business.storage_delete_failed', ['driver' => 'aliyun', 'error' => $e->getMessage()]));
        }
        if (!$exists) {
            // OSS 删不存在的对象也返回成功，先查一次才能满足接口「不存在返回 false」的约定
            return false;
        }

        try {
            $this->client->deleteObject(new DeleteObjectRequest($this->bucket, $key));
        } catch (\Throwable $e) {
            throw new BusinessException(lang('business.storage_delete_failed', ['driver' => 'aliyun', 'error' => $e->getMessage()]));
        }

        return true;
    }

    public function exists(string $relativePath): bool
    {
        try {
            return $this->client->isObjectExist($this->bucket, self::normalize($relativePath));
        } catch (\Throwable) {
            return false;
        }
    }

    private static function normalize(string $relativePath): string
    {
        return ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    /** 去掉协议与尾部斜杠，只留主机名。 */
    private static function hostOf(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        $endpoint = (string) preg_replace('#^https?://#i', '', $endpoint);

        return rtrim($endpoint, '/');
    }

    /**
     * 地域解析：显式配置的 `storage_oss_region` 优先（前后空白 trim 掉），其次从 endpoint 推导；
     * 两条都拿不到就明确报错——此时任何请求都会在 V4 签名阶段失败，早报比晚报好。
     */
    private static function resolveRegion(string $configured, string $endpointHost): string
    {
        $configured = trim($configured);
        if ($configured !== '') {
            return $configured;
        }

        return self::deriveRegion($endpointHost)
            ?? throw new BusinessException(lang('business.storage_oss_region_required'));
    }

    /**
     * 从 endpoint 主机名推导 region：`oss-cn-hangzhou.aliyuncs.com`、
     * `oss-cn-hangzhou-internal.aliyuncs.com` 都得到 `cn-hangzhou`；
     * 自定义域名之类认不出来的 endpoint 返回 null，由调用方决定怎么报错。
     */
    private static function deriveRegion(string $endpointHost): ?string
    {
        if (preg_match('/^oss-([a-z0-9-]+?)(-internal)?\.aliyuncs\.com$/i', $endpointHost, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }
}
