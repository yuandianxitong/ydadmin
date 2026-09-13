<?php

declare(strict_types=1);

namespace tests\fixtures\ApiDoc;

/**
 * RuleReflector 测试夹具：与 app\service\system\AdminService::passwordRule()、
 * app\service\system\DictionaryService::MAX_BATCH_CODES 同构，用来证明反射必须走容器
 * 拿到「依赖已注入完毕」的控制器实例——newInstanceWithoutConstructor() 拿到的实例访问
 * $this->service 会直接 Error（typed property 未初始化），而不是优雅降级。
 */
final class FixtureRuleService
{
    public const MAX_BATCH = 5;

    public function passwordRule(): string
    {
        return 'string|min:6|max:20';
    }
}
