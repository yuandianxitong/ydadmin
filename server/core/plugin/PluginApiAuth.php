<?php

declare(strict_types=1);

namespace core\plugin;

use Attribute;

/**
 * 标记插件 C 端控制器（或其中某个动作）需要登录态。
 * 标在方法上时覆盖类上的标记；方法未标则继承类。
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class PluginApiAuth
{
}
