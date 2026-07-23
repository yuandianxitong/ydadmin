<?php
// tests/Unit/YdSpec/YdSpecValidatorTest.php
declare(strict_types=1);

namespace tests\Unit\YdSpec;

use core\ai\ydspec\YdSpecValidator;
use tests\TestCase;

class YdSpecValidatorTest extends TestCase
{
    private function validSpec(): array
    {
        return [
            'version' => 'ydspec/v1',
            'module'  => ['name' => 'appointment', 'title' => '预约管理'],
            'entities' => [[
                'name' => 'Appointment', 'table' => 'appointments',
                'kind' => 'business', 'soft_delete' => 'soft',
                'fields' => [
                    ['name' => 'appointment_no', 'type' => 'string', 'length' => 64, 'nullable' => false, 'unique' => true],
                    ['name' => 'paid_amount', 'type' => 'decimal', 'precision' => 12, 'scale' => 2, 'nullable' => false],
                ],
                'indexes' => [['fields' => ['appointment_no'], 'type' => 'unique']],
            ]],
        ];
    }

    public function testValidSpecPassesStructure(): void
    {
        $this->assertSame([], (new YdSpecValidator())->validateStructure($this->validSpec()));
    }

    public function testMissingModuleFailsStructure(): void
    {
        $spec = $this->validSpec();
        unset($spec['module']);
        $this->assertNotEmpty((new YdSpecValidator())->validateStructure($spec));
    }

    public function testDecimalWithoutPrecisionFailsStructure(): void
    {
        $spec = $this->validSpec();
        $spec['entities'][0]['fields'][1] = ['name' => 'paid_amount', 'type' => 'decimal'];
        $this->assertNotEmpty((new YdSpecValidator())->validateStructure($spec));
    }

    public function testFloatMoneyIsError(): void
    {
        $spec = $this->validSpec();
        $spec['entities'][0]['fields'][1] = ['name' => 'paid_amount', 'type' => 'int'];
        $issues = (new YdSpecValidator())->validateSemantics($spec);
        $this->assertContains('money-decimal', array_column($issues, 'rule'));
    }

    public function testReservedFieldIsError(): void
    {
        $spec = $this->validSpec();
        $spec['entities'][0]['fields'][] = ['name' => 'created_at', 'type' => 'datetime'];
        $issues = (new YdSpecValidator())->validateSemantics($spec);
        $errors = array_filter($issues, fn ($i) => $i['severity'] === 'error');
        $this->assertContains('reserved-field', array_column($errors, 'rule'));
    }

    public function testLogEntityMustNotSoftDelete(): void
    {
        $spec = $this->validSpec();
        $spec['entities'][0]['kind'] = 'log';
        $spec['entities'][0]['soft_delete'] = 'soft';
        $issues = (new YdSpecValidator())->validateSemantics($spec);
        $this->assertContains('log-append-only', array_column($issues, 'rule'));
    }

    public function testUnknownRelationTargetIsError(): void
    {
        $spec = $this->validSpec();
        $spec['entities'][0]['fields'][] = [
            'name' => 'ghost_id', 'type' => 'bigint',
            'relation' => ['to' => 'Ghost', 'kind' => 'belongsTo'],
        ];
        $issues = (new YdSpecValidator())->validateSemantics($spec);
        $this->assertContains('relation-target', array_column($issues, 'rule'));
    }

    public function testBusinessNoWithoutUniqueWarns(): void
    {
        $spec = $this->validSpec();
        $spec['entities'][0]['fields'][0]['unique'] = false;
        $issues = (new YdSpecValidator())->validateSemantics($spec);
        $warns = array_filter($issues, fn ($i) => $i['severity'] === 'warn');
        $this->assertContains('business-no-unique', array_column($warns, 'rule'));
    }

    public function testCleanSpecHasNoErrorSeverityIssues(): void
    {
        $issues = (new YdSpecValidator())->validateSemantics($this->validSpec());
        $errors = array_filter($issues, fn ($i) => $i['severity'] === 'error');
        $this->assertSame([], array_values($errors));
    }
}
