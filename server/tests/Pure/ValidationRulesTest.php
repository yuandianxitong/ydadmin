<?php

declare(strict_types=1);

namespace tests\Pure;

use core\query\QueryInput;
use core\validation\CommonRules;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;

final class ValidationRulesTest extends TestCase
{
    public function test_normalized_query_validates_and_drops_undeclared_fields(): void
    {
        $factory = new Factory(new Translator(new ArrayLoader(), 'en'));
        $input = QueryInput::normalize(['status' => '0', 'page' => '2', 'keyword' => '', 'is_admin' => 1], ['keyword']);
        $validator = $factory->make($input, CommonRules::pagination() + ['status' => 'sometimes|integer|in:0,1', 'keyword' => 'sometimes|string']);
        $this->assertSame(['page' => '2', 'status' => '0'], $validator->validated());
        $this->assertTrue($factory->make(['page' => ['2']], CommonRules::pagination())->fails());
    }

    public function test_nested_object_whitelist_and_update_presence_semantics(): void
    {
        $factory = new Factory(new Translator(new ArrayLoader(), 'en'));
        $rules = ['name' => 'sometimes|required|string', 'settings' => 'sometimes|array:theme', 'settings.theme' => 'sometimes|string'];
        $this->assertFalse($factory->make([], $rules)->fails());
        $this->assertTrue($factory->make(['name' => ''], $rules)->fails());
        $this->assertTrue($factory->make(['settings' => ['theme' => 'dark', 'is_admin' => true]], $rules)->fails());
    }
}
