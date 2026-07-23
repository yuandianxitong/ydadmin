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
}
