<?php

declare(strict_types=1);

namespace tests\Feature\Plugin\Support;

use core\plugin\saga\PluginSagaRunner;
use core\plugin\saga\ReloadVerifier;

/** 测试专用：没有 master / worker 池，重载信号与生效校验都直接放行。 */
final class ImmediatePluginSagaRunner extends PluginSagaRunner
{
    protected function triggerReload(): void
    {
    }

    protected function reloadVerifier(): ReloadVerifier
    {
        return new class extends ReloadVerifier {
            public function __construct()
            {
            }

            public function waitUntilEffective(string $expected, ?int $timeoutSeconds = null): string
            {
                return $expected;
            }
        };
    }
}
