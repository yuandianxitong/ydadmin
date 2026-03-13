<?php
declare(strict_types=1);

namespace core\storage\driver;

use core\storage\StorageInterface;
use core\exception\BusinessException;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\BucketManager;

class QiniuDriver implements StorageInterface
{
    protected Auth $auth;
    protected string $bucket;
    protected string $domain;

    public function __construct(array $config)
    {
        if (empty($config['access_key']) || empty($config['secret_key']) || empty($config['bucket']) || empty($config['domain'])) {
            throw new BusinessException(lang('business.qiniu_config_incomplete'));
        }

        $this->auth = new Auth($config['access_key'], $config['secret_key']);
        $this->bucket = $config['bucket'];
        $this->domain = rtrim($config['domain'], '/');
    }

    public function upload(string $localPath, string $remotePath): string
    {
        try {
            $token = $this->auth->uploadToken($this->bucket, $remotePath);
            $uploadMgr = new UploadManager();
            [$ret, $err] = $uploadMgr->putFile($token, $remotePath, $localPath);

            if ($err !== null) {
                throw new BusinessException(lang('business.qiniu_upload_failed') . ': ' . $err->message());
            }

            return $this->getUrl($remotePath);
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.qiniu_upload_failed') . ': ' . $e->getMessage());
        }
    }

    public function delete(string $path): bool
    {
        try {
            $bucketMgr = new BucketManager($this->auth);
            [$ret, $err] = $bucketMgr->delete($this->bucket, $path);

            if ($err !== null) {
                throw new BusinessException(lang('business.qiniu_delete_failed') . ': ' . $err->message());
            }

            return true;
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new BusinessException(lang('business.qiniu_delete_failed') . ': ' . $e->getMessage());
        }
    }

    public function exists(string $path): bool
    {
        try {
            $bucketMgr = new BucketManager($this->auth);
            [$ret, $err] = $bucketMgr->stat($this->bucket, $path);

            return $err === null;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getUrl(string $path): string
    {
        return $this->domain . '/' . ltrim($path, '/');
    }

    public function getDriver(): string
    {
        return 'qiniu';
    }
}
