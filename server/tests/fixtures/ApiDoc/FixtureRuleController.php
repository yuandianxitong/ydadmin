<?php

declare(strict_types=1);

namespace tests\fixtures\ApiDoc;

use DI\Attribute\Inject;

/**
 * RuleReflector 的测试夹具：只放 {action}Rules() 私有方法本身，不写完整的控制器动作体
 * （不需要 Request/Response，RuleReflector 从不调用公开动作）。四个方法对应
 * rulesFor() 的四种分支：
 *   - plain()   纯字面量规则，最常见的一类
 *   - reset()   同构 AdminController::resetPassword：规则里拼了注入服务的方法调用
 *   - batch()   同构 DictionaryController::batchOptions：规则里拼了注入服务的类常量
 *   - noParams  没有 noParamsRules()：37 个无请求体动作里的一类
 *   - broken()  规则方法自己抛异常：模拟收敛没覆盖到的坏端点，验证单点故障不致命
 */
final class FixtureRuleController
{
    #[Inject]
    protected FixtureRuleService $service;

    private function plainRules(): array
    {
        return ['name' => 'required|string|max:50'];
    }

    private function resetRules(): array
    {
        return ['password' => 'required|' . $this->service->passwordRule()];
    }

    private function batchRules(): array
    {
        return ['codes' => 'required|array|max:' . FixtureRuleService::MAX_BATCH];
    }

    private function brokenRules(): array
    {
        throw new \RuntimeException('规则方法本身抛异常：模拟收敛未覆盖到的坏端点');
    }
}
