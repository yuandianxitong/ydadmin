<?php

declare(strict_types=1);

namespace tests\Unit\ApiDoc;

use core\apidoc\RuleReflector;
use support\Container;
use tests\fixtures\ApiDoc\FixtureRuleController;
use tests\TestCase;

final class RuleReflectorTest extends TestCase
{
    private RuleReflector $reflector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflector = new RuleReflector(
            static fn (string $class): object => Container::get($class),
        );
    }

    public function test_returns_plain_literal_rules(): void
    {
        $rules = $this->reflector->rulesFor(FixtureRuleController::class, 'plain');

        $this->assertSame(['name' => 'required|string|max:50'], $rules);
    }

    public function test_resolves_through_the_container_so_injected_service_methods_are_usable(): void
    {
        $rules = $this->reflector->rulesFor(FixtureRuleController::class, 'reset');

        $this->assertSame(['password' => 'required|string|min:6|max:20'], $rules);
    }

    public function test_resolves_constant_concatenation_via_the_container_instance(): void
    {
        $rules = $this->reflector->rulesFor(FixtureRuleController::class, 'batch');

        $this->assertSame(['codes' => 'required|array|max:5'], $rules);
    }

    public function test_missing_rules_method_returns_null_without_a_warning(): void
    {
        $rules = $this->reflector->rulesFor(FixtureRuleController::class, 'noParams');

        $this->assertNull($rules);
        $this->assertSame([], $this->reflector->warnings());
    }

    public function test_throwing_rules_method_is_downgraded_to_null_with_a_warning_instead_of_fataling(): void
    {
        $rules = $this->reflector->rulesFor(FixtureRuleController::class, 'broken');

        $this->assertNull($rules);
        $warnings = $this->reflector->warnings();
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString(FixtureRuleController::class, $warnings[0]);
        $this->assertStringContainsString('brokenRules', $warnings[0]);
    }

    public function test_resolver_failure_is_downgraded_to_null_with_a_warning(): void
    {
        $reflector = new RuleReflector(static function (string $class): object {
            throw new \RuntimeException('container 找不到 ' . $class);
        });

        $rules = $reflector->rulesFor(FixtureRuleController::class, 'plain');

        $this->assertNull($rules);
        $this->assertCount(1, $reflector->warnings());
        $this->assertStringContainsString('container 找不到', $reflector->warnings()[0]);
    }

    public function test_missing_controller_class_is_downgraded_to_null_with_a_warning(): void
    {
        $rules = $this->reflector->rulesFor('tests\\Fixtures\\ApiDoc\\DoesNotExist', 'plain');

        $this->assertNull($rules);
        $this->assertCount(1, $this->reflector->warnings());
        $this->assertStringContainsString('DoesNotExist', $this->reflector->warnings()[0]);
    }

    public function test_autoload_failure_during_class_exists_is_downgraded_to_null_with_a_warning(): void
    {
        $explodingClass = 'tests\\fixtures\\ApiDoc\\AutoloadExplodingController';
        $autoloader = static function (string $class) use ($explodingClass): void {
            if ($class === $explodingClass) {
                throw new \Error('simulated autoload failure');
            }
        };

        spl_autoload_register($autoloader, true, true);

        try {
            $rules = $this->reflector->rulesFor($explodingClass, 'store');

            $this->assertNull($rules);
            $this->assertCount(1, $this->reflector->warnings());
            $this->assertStringContainsString($explodingClass, $this->reflector->warnings()[0]);
        } finally {
            spl_autoload_unregister($autoloader);
        }
    }

    /**
     * 反证 §核心约束：newInstanceWithoutConstructor() 拿到的实例，$service 是
     * uninitialized typed property，调用 resetRules() 会直接 Error 而不是优雅降级——
     * 这正是 RuleReflector 禁止走这条路径、必须经容器解析闭包的原因。本用例把这条
     * 被禁止的路径单独跑一遍，钉死这个致命错误确实存在（不是臆测）。
     */
    public function test_the_forbidden_uninitialised_instance_path_actually_fatals(): void
    {
        $reflection = new \ReflectionClass(FixtureRuleController::class);
        $uninitialised = $reflection->newInstanceWithoutConstructor();
        $rulesMethod = new \ReflectionMethod($uninitialised, 'resetRules');
        $rulesMethod->setAccessible(true);

        $this->expectException(\Error::class);
        $rulesMethod->invoke($uninitialised);
    }
}
