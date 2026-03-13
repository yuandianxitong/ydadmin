<?php
declare(strict_types=1);

namespace core\storage\driver;

use core\storage\StorageInterface;
use core\exception\BusinessException;
use OSS\OssClient;

class AliyunOssDriver implements StorageInterface
{
    protected OssClient $client;
    protected string $bucket;
    protected string $domain;

    public function __construct(array $config)
    {
        if (empty($config['access_key']) || empty($config['access_secret']) || empty($config['bucket']) || empty($config['endpoint'])) {
            throw new BusinessException(lang('business.aliyun_oss_config_incomplete'));
        }

        $this->client = new OssClient(
            $config['access_key'],
            $config['access_secret'],
            $config['endpoint']
        );
        $this->bucket = $config['bucket'];
        $this->domain = rtrim($config['domain'] ?? '', '/');
    }

    public function upload(string $localPath, string $remotePath): string
    {
        try {
            $this->client->uploadFile($this->bucket, $remotePath, $localPath);
            return $this->getUrl($remotePath);
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.aliyun_oss_upload_failed') . ': ' . $e->getMessage());
        }
    }

    public function delete(string $path): bool
    {
        try {
            $this->client->deleteObject($this->bucket, $path);
            return true;
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.aliyun_oss_delete_failed') . ': ' . $e->getMessage());
        }
    }

    public function exists(string $path): bool
    {
        try {
            return $this->client->doesObjectExist($this->bucket, $path);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getUrl(string $path): string
    {
        if ($this->domain) {
            return $this->domain . '/' . ltrim($path, '/');
        }

        // 从 endpoint 构造 URL
        try {
            return $this->client->signUrl($this->bucket, $path, 3600);
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.aliyun_oss_url_failed') . ': ' . $e->getMessage());
        }
    }

    public function getDriver(): string
    {
        return 'aliyun';
    }
}
