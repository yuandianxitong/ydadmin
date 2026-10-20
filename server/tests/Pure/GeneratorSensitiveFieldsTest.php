<?php

declare(strict_types=1);

namespace tests\Pure;

use core\generator\GeneratorRequest;
use core\generator\ModuleBlueprint;
use core\generator\TableDefinition;
use core\generator\TemplateRenderer;
use core\generator\TypeInference;
use PHPUnit\Framework\TestCase;

final class GeneratorSensitiveFieldsTest extends TestCase
{
    public function test_sensitive_fields_cannot_be_reenabled_in_responses_by_overrides(): void
    {
        $inference = new TypeInference();
        $columns = [];
        foreach (['id', 'password', 'api_secret', 'access_token'] as $name) {
            $columns[] = $inference->describe(['Field' => $name, 'Type' => $name === 'id' ? 'int' : 'varchar(255)', 'Key' => $name === 'id' ? 'PRI' : '', 'Null' => 'NO', 'Default' => $name === 'password' ? '' : null])
                ->withOverrides('input', true, true, true);
        }
        $blueprint = new ModuleBlueprint(new TableDefinition('sensitive_records', '敏感信息', $columns), new GeneratorRequest('sensitive_records', 'demo', 'SensitiveRecord', '', []));
        $renderer = new TemplateRenderer(dirname(__DIR__, 2) . '/core/generator/stubs');
        $artifacts = [];
        foreach ($blueprint->artifacts() as $key => $artifact) {
            $artifacts[$key] = $renderer->render($artifact['stub'], $artifact['vars']);
        }
        foreach (['password', 'api_secret', 'access_token'] as $name) {
            self::assertStringNotContainsString("'{$name}' =>", $artifacts['resource']);
            self::assertStringNotContainsString('row.' . $name, $artifacts['page']);
        }
        self::assertStringNotContainsString('form.api_secret', $artifacts['form']);
        self::assertStringNotContainsString('form.access_token', $artifacts['form']);
        self::assertStringContainsString('type="password"', $artifacts['form']);
        self::assertStringContainsString('password_hash(', $artifacts['service']);
        self::assertStringContainsString('new NotFoundException', $artifacts['service']);
        preg_match('/private function sensitiveRecordRules\(string \$scene\): array\s*(\{.*?\n    \})/s', $artifacts['controller'], $matches);
        $rules = eval('return function (string $scene): array ' . $matches[1] . ';');
        self::assertSame('required|string|max:255', $rules('create')['password']);
        self::assertSame('sometimes|nullable|string|max:255', $rules('update')['password']);
        \core\validation\ValidatorFactory::validate(['password' => ''], $rules('update'));
        $this->expectException(\core\exception\ValidationException::class);
        \core\validation\ValidatorFactory::validate([], $rules('create'));

    }
}
