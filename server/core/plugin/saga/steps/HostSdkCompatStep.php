<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\plugin\HostSdkCompat;
use core\saga\SagaContext;

/**
 * install saga 第 2 步：Host SDK 版本区间 + externals 白名单兼容闸。
 */
final class HostSdkCompatStep extends AbstractPluginSagaStep
{
    public function name(): string
    {
        return 'HostSdkCompat';
    }

    /**
     * 判定基准取发起安装时冻结进 payload 的 host_sdk_version，而不是子进程运行时再读 config：
     * 部署换版本可能与 saga 排队重叠，同一次安装必须用同一份版本值。历史行缺这个键时回退到 config。
     */
    public function execute(SagaContext $ctx): void
    {
        $hostSdkVersion = (string) ($this->payload()['host_sdk_version'] ?? config('version.host_sdk'));
        HostSdkCompat::assertPluginCompatible($this->pluginDir(), $this->code(), $hostSdkVersion);
    }

    /** 纯校验，无副作用，无需补偿。 */
    public function compensate(SagaContext $ctx): void
    {
    }
}
