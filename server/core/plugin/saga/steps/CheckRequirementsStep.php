<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\plugin\contracts\PluginStore;
use core\plugin\ManifestReader;
use core\plugin\PluginRequirements;
use core\saga\SagaContext;

/**
 * 服务层预检与 saga 实际执行之间隔着排队窗口，依赖可能在此期间被禁用，这里按执行时刻的状态重判。
 */
final class CheckRequirementsStep extends AbstractPluginSagaStep
{
    public function __construct(array $saga, private readonly PluginStore $pluginRepo)
    {
        parent::__construct($saga);
    }

    public function name(): string
    {
        return 'CheckRequirements';
    }

    public function execute(SagaContext $ctx): void
    {
        $plugin = $this->pluginRepo->find($this->pluginId()) ?? [];
        PluginRequirements::assertPlatform(
            ManifestReader::decodeColumn($plugin['manifest'] ?? null),
            (string) ($this->payload()['core_version'] ?? config('version.version')),
            PluginRequirements::enabledCodes($this->pluginRepo)
        );
    }

    public function compensate(SagaContext $ctx): void
    {
    }
}
