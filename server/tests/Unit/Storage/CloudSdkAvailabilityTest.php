<?php

declare(strict_types=1);

namespace tests\Unit\Storage;

use AlibabaCloud\Oss\V2\Client as OssClient;
use AlibabaCloud\Oss\V2\Config as OssConfig;
use AlibabaCloud\Oss\V2\Credentials\StaticCredentialsProvider;
use Qcloud\Cos\Client as CosClient;
use tests\TestCase;

/**
 * 云存储 SDK 可用性（spec §2.3 的 M1c 首个任务）。
 *
 * 只构造客户端对象、不发网络请求：验证的是「在 PHP 8.4 下能加载、能实例化，且不触发
 * deprecation / warning / notice」。phpunit.xml 的 failOnWarning + failOnDeprecation
 * 是本测试的另一半——SDK 一旦在构造路径上抛废弃告警，这里就红。
 *
 * 七牛的 SDK（qiniu/php-sdk，最新版 v7.14.0）不在此列，也不允许被引入：它在 PHP 8.4 下
 * 有 3 条「隐式可空参数」废弃告警，其中 Qiniu\Config 那条由 composer files 自动加载触发，
 * 每个 PHP 进程启动就会打一条，且没有修好的 tag 可锁。七牛驱动改为用 Guzzle 发签名
 * HTTP 请求实现（见 M1c 计划 Task 1 的决策表与 Task 4）。
 */
final class CloudSdkAvailabilityTest extends TestCase
{
    public function test_aliyun_oss_client_constructs(): void
    {
        $client = new OssClient(new OssConfig(
            region: 'cn-hangzhou',
            endpoint: 'oss-cn-hangzhou.aliyuncs.com',
            credentialsProvider: new StaticCredentialsProvider('probe-ak', 'probe-sk'),
        ));

        $this->assertInstanceOf(OssClient::class, $client);
    }

    public function test_tencent_cos_client_constructs(): void
    {
        $client = new CosClient([
            'region'      => 'ap-guangzhou',
            'schema'      => 'https',
            'credentials' => ['secretId' => 'probe-id', 'secretKey' => 'probe-key'],
        ]);

        $this->assertInstanceOf(CosClient::class, $client);
    }

    /** 决策守卫：七牛 SDK 不得进入依赖树（理由见类注释）。引入它这条会红。 */
    public function test_qiniu_sdk_is_not_installed(): void
    {
        $this->assertFalse(class_exists('Qiniu\\Auth'), 'qiniu/php-sdk 在 PHP 8.4 下有废弃告警，七牛驱动用签名 HTTP 实现');
    }
}
