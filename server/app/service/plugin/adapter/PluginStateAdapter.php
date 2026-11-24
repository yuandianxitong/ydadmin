<?php

declare(strict_types=1);

namespace app\service\plugin\adapter;

use core\plugin\contracts\PluginStateListener;

/** plugins 表没有宿主专有列：启用时不额外写列，状态变化也无后续动作。 */
final class PluginStateAdapter implements PluginStateListener
{
    public function enabledColumns(string $code, array $manifest): array
    {
        return [];
    }

    public function statusChanged(): void
    {
    }
}
