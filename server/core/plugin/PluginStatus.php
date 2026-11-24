<?php

declare(strict_types=1);

namespace core\plugin;

final class PluginStatus
{
    public const ENABLED = 1;
    public const DISABLED = 2;
    public const INSTALLING = 3;
    public const UPGRADING = 4;
    public const FAILED = 5;
    public const UNINSTALLED = 6;

    public const MENU_ENABLED = 1;
}
