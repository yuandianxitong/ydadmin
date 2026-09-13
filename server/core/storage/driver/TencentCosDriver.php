<?php

declare(strict_types=1);

namespace core\storage\driver;

use core\exception\BusinessException;
use core\storage\StorageInterface;
use Qcloud\Cos\Client;
use Qcloud\Cos\Exception\ServiceResponseException;
use RuntimeException;

/**
 * 腾讯云 COS 驱动（SDK：qcloud/cos-sdk-v5）。
 *
 * 调用形状与 TP8 一致：`upload()` 落对象、`DeleteObject()`/`HeadObject()` 用数组参数
 * （大小写按 SDK 的 `@method` 注解原名，`__call()` 内部会 `ucfirst()`，写成 deleteObject
 * 效果一样，但只有原名 phpstan 才认得是已声明的魔术方法）。
 * 差异只有两处：凭据不全时抛 `business.storage_config_incomplete`（TP8 是自造的中文串），
 * 以及 `delete()` 先 `HeadObject` 查存在性——COS 删不存在的 Key 也返回成功，不先查就永远
 * 返回 true；且查存在性的异常要按状态码区分「真的不存在」（404）与其余真失败，见 `delete()`
 * 的方法注释。
 */
final class TencentCosDriver implements StorageInterface
{
    private readonly Client $client;

    private readonly string $bucket;

    private readonly string $region;

    /** 自定义访问域名，已去掉尾部 `/` */
    private readonly string $domain;

    /**
     * @param array{secret_id: string, secret_key: string, bucket: string, region: string, domain: string} $config
     */
    public function __construct(array $config)
    {
        foreach (['secret_id', 'secret_key', 'bucket', 'region'] as $required) {
            // trim 之后再判断：管理端是文本输入框，纯空格必须当「没填」处理
            if (trim((string) ($config[$required] ?? '')) === '') {
                throw new BusinessException(lang('business.storage_config_incomplete'));
            }
        }

        $this->bucket = $config['bucket'];
        $this->region = $config['region'];
        $this->domain = rtrim($config['domain'], '/');

        $this->client = new Client([
            'region'      => $this->region,
            'schema'      => 'https',
            'credentials' => [
                'secretId'  => $config['secret_id'],
                'secretKey' => $config['secret_key'],
            ],
        ]);
    }

    public function put(string $localTmpPath, string $targetRelativePath): void
    {
        if (!is_file($localTmpPath)) {
            throw new RuntimeException("源文件不存在: {$localTmpPath}");
        }

        $handle = fopen($localTmpPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("源文件无法读取: {$localTmpPath}");
        }

        try {
            $this->client->upload($this->bucket, self::normalize($targetRelativePath), $handle);
        } catch (\Throwable $e) {
            throw new BusinessException(lang('business.storage_upload_failed', ['driver' => 'tencent', 'error' => $e->getMessage()]));
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        @unlink($localTmpPath);
    }

    public function getUrl(string $relativePath): string
    {
        $key = self::normalize($relativePath);
        if ($this->domain !== '') {
            return $this->domain . '/' . $key;
        }

        return "https://{$this->bucket}.cos.{$this->region}.myqcloud.com/{$key}";
    }

    /**
     * 🔴 delete() 不能借道 exists() 的布尔返回值——`exists()` 把「真的不存在」与「查不了
     * （凭据错/权限不足/网络问题）」压成了同一个 false，密钥配错时 delete() 就会表现成
     * 「悄悄删了个不存在的文件」而不是报错。这里自己 HeadObject 一次并区分异常类型：
     * COS 对 HEAD 请求返回 404 时才是「真的不存在」（`ExceptionParser::parseHeaders()`
     * 把这种情况编码成 `getStatusCode() === 404`），其余任何异常（403/5xx/网络问题）
     * 都当真失败，转成 `storage_delete_failed`。
     */
    public function delete(string $relativePath): bool
    {
        $key = self::normalize($relativePath);
        try {
            // 大小写敏感：SDK 的 @method 注解写的是 HeadObject/DeleteObject（__call() 内部会
            // ucfirst()，两种写法运行时等价，用注解原名是为了让 phpstan 认得这个魔术方法）。
            $this->client->HeadObject(['Bucket' => $this->bucket, 'Key' => $key]);
        } catch (\Throwable $e) {
            if ($this->isMissingObjectException($e)) {
                return false;
            }

            throw new BusinessException(lang('business.storage_delete_failed', ['driver' => 'tencent', 'error' => $e->getMessage()]));
        }

        try {
            $this->client->DeleteObject(['Bucket' => $this->bucket, 'Key' => $key]);
        } catch (\Throwable $e) {
            throw new BusinessException(lang('business.storage_delete_failed', ['driver' => 'tencent', 'error' => $e->getMessage()]));
        }

        return true;
    }

    public function exists(string $relativePath): bool
    {
        try {
            $this->client->HeadObject(['Bucket' => $this->bucket, 'Key' => self::normalize($relativePath)]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** COS 对 HEAD 请求返回 404 才是「真的不存在」；其余状态码（403/5xx…）都是真失败。 */
    private function isMissingObjectException(\Throwable $e): bool
    {
        return $e instanceof ServiceResponseException && $e->getStatusCode() === 404;
    }

    private static function normalize(string $relativePath): string
    {
        return ltrim(str_replace('\\', '/', $relativePath), '/');
    }
}
