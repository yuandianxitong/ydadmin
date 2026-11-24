<?php

declare(strict_types=1);

namespace core\plugin;

final class PluginCodePattern
{
    /** plugin.json 的 code 与 entitlement。 */
    public const MANIFEST = '/^[a-z][a-z0-9-]{1,79}$/D';

    /** 静态资源路径里的 code，允许下划线（shop 时代的 flash_sale 等）。 */
    public const ASSET = '/^[a-z0-9][a-z0-9_-]*$/D';

    public const ICON_FILE = '/^[a-z0-9][a-z0-9._-]{0,63}\.(png|jpg|jpeg|svg|webp|ico|gif)$/iD';
}
