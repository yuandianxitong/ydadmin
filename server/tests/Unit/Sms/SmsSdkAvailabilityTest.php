<?php

declare(strict_types=1);

namespace tests\Unit\Sms;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use Darabonba\OpenApi\Models\Config;
use tests\TestCase;

/**
 * 短信 SDK 可用性（照 M1c 的 CloudSdkAvailabilityTest 办）。只构造客户端对象、不发网络请求：
 * 验证的是「在 PHP 8.4 下能加载、能实例化，且不触发 deprecation / warning / notice」——
 * phpunit.xml 的 failOnWarning + failOnDeprecation 是这条测试的另一半。SDK 一旦在构造路径上
 * 抛废弃告警，这里就红，那时按计划「设计决定」第 8 条回到评审桌上（备选是照 QiniuDriver 手写签名 HTTP 实现）。
 */
final class SmsSdkAvailabilityTest extends TestCase
{
    public function test_aliyun_sms_client_constructs(): void
    {
        $client = new Dysmsapi(new Config([
            'accessKeyId'     => 'probe-ak',
            'accessKeySecret' => 'probe-sk',
            'endpoint'        => 'dysmsapi.aliyuncs.com',
        ]));

        $this->assertInstanceOf(Dysmsapi::class, $client);
    }

    /**
     * 决策守卫（计划「设计决定」第 8 条）：两个短信 SDK 都装会把依赖树撑大，所以只引入阿里云，
     * 腾讯云驱动按 class_exists 判定。真要用腾讯云时执行 composer require tencentcloud/sms，
     * 那时本条会红——同时要改的还有 SmsDriverConfigTest 里对应的那条断言。
     */
    public function test_tencent_sms_sdk_is_not_installed_by_default(): void
    {
        $this->assertFalse(class_exists('TencentCloud\\Sms\\V20210111\\SmsClient'));
    }
}
