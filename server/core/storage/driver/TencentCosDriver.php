<?php
declare(strict_types=1);

namespace core\storage\driver;

use core\storage\StorageInterface;
use core\exception\BusinessException;

class TencentCosDriver implements StorageInterface
{
    protected \Qcloud\Cos\Client $client;
    protected string $bucket;
    protected string $region;
    protected string $domain;

    public function __construct(array $config)
    {
        if (empty($config['secret_id']) || empty($config['secret_key']) || empty($config['bucket']) || empty($config['region'])) {
            throw new BusinessException(lang('business.tencent_cos_config_incomplete'));
        }

        $this->bucket = $config['bucket'];
        $this->region = $config['region'];
        $this->domain = rtrim($config['domain'] ?? '', '/');

        $this->client = new \Qcloud\Cos\Client([
            'region'      => $this->region,
            'schema'      => 'https',
            'credentials' => [
                'secretId'  => $config['secret_id'],
                'secretKey' => $config['secret_key'],
            ],
        ]);
    }

    public function upload(string $localPath, string $remotePath): string
    {
        try {
            $this->client->upload($this->bucket, $remotePath, fopen($localPath, 'rb'));
            return $this->getUrl($remotePath);
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.tencent_cos_upload_failed') . ': ' . $e->getMessage());
        }
    }

    public function delete(string $path): bool
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $path,
            ]);
            return true;
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.tencent_cos_delete_failed') . ': ' . $e->getMessage());
        }
    }

    public function exists(string $path): bool
    {
        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key'    => $path,
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getUrl(string $path): string
    {
        if ($this->domain) {
            return $this->domain . '/' . ltrim($path, '/');
        }

        return "https://{$this->bucket}.cos.{$this->region}.myqcloud.com/" . ltrim($path, '/');
    }

    public function getDriver(): string
    {
        return 'tencent';
    }
}
