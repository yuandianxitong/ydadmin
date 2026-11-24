<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\plugin\PluginSqlRunner;
use core\saga\SagaContext;

/**
 * install saga 第 4 步：跑 `database/install.sql`。
 *
 * 历史 updates/ 只登记不重跑；软卸载后重装时已执行过的部分同样跳过。
 */
final class SqlInstallStep extends AbstractPluginSagaStep
{
    public function __construct(array $saga, private readonly PluginSqlRunner $sqlRunner)
    {
        parent::__construct($saga);
    }

    public function name(): string
    {
        return 'SqlInstall';
    }

    public function execute(SagaContext $ctx): void
    {
        $this->sqlRunner->install(
            $this->pluginDir(),
            $this->pluginId(),
            $this->code(),
            (string) ($this->payload()['version'] ?? '')
        );
    }

    /**
     * 不补偿：install.sql 没有 down 脚本，安装失败不删可能已有数据的业务表，与软卸载保留业务表一致。
     * 清库只走 purge（uninstall.sql）。
     */
    public function compensate(SagaContext $ctx): void
    {
    }
}
