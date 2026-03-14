<?php
declare(strict_types=1);

namespace tests\Unit;

use PHPUnit\Framework\TestCase;
use core\helper\StringHelper;
use core\helper\ArrayHelper;
use core\helper\DateHelper;

class HelperTest extends TestCase
{
    // ==================== StringHelper ====================

    public function testRandomGeneratesCorrectLength(): void
    {
        $result = StringHelper::random(16);
        $this->assertEquals(16, strlen($result));
    }

    public function testRandomWithCustomChars(): void
    {
        $result = StringHelper::random(10, '0123456789');
        $this->assertMatchesRegularExpression('/^\d{10}$/', $result);
    }

    public function testUuidFormat(): void
    {
        $uuid = StringHelper::uuid();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid);
    }

    public function testSnakeCase(): void
    {
        $this->assertEquals('hello_world', StringHelper::snake('helloWorld'));
        $this->assertEquals('user_name', StringHelper::snake('userName'));
        $this->assertEquals('get_user_list', StringHelper::snake('getUserList'));
    }

    public function testCamelCase(): void
    {
        $this->assertEquals('helloWorld', StringHelper::camel('hello_world'));
        $this->assertEquals('userName', StringHelper::camel('user_name'));
        $this->assertEquals('getUserList', StringHelper::camel('get_user_list'));
    }

    public function testPascalCase(): void
    {
        $this->assertEquals('HelloWorld', StringHelper::pascal('hello_world'));
        $this->assertEquals('UserName', StringHelper::pascal('user_name'));
    }

    public function testSubstr(): void
    {
        $this->assertEquals('Hello...', StringHelper::substr('Hello World', 5));
        $this->assertEquals('Short', StringHelper::substr('Short', 10));
    }

    public function testSubstrWithCustomSuffix(): void
    {
        $this->assertEquals('Hello~~', StringHelper::substr('Hello World', 5, '~~'));
    }

    public function testMask(): void
    {
        $this->assertEquals('138****8000', StringHelper::mask('13800138000', 3, 4));
        $this->assertEquals('张*三', StringHelper::mask('张大三', 1, 1));
    }

    public function testMaskShortString(): void
    {
        $this->assertEquals('ab', StringHelper::mask('ab', 5, 4));
    }

    public function testSlug(): void
    {
        $this->assertEquals('hello-world', StringHelper::slug('Hello World'));
        $this->assertEquals('hello_world', StringHelper::slug('Hello World', '_'));
    }

    public function testStartsWith(): void
    {
        $this->assertTrue(StringHelper::startsWith('Hello World', 'Hello'));
        $this->assertFalse(StringHelper::startsWith('Hello World', 'World'));
    }

    public function testEndsWith(): void
    {
        $this->assertTrue(StringHelper::endsWith('Hello World', 'World'));
        $this->assertFalse(StringHelper::endsWith('Hello World', 'Hello'));
    }

    public function testContains(): void
    {
        $this->assertTrue(StringHelper::contains('Hello World', 'lo Wo'));
        $this->assertFalse(StringHelper::contains('Hello World', 'xyz'));
    }

    // ==================== ArrayHelper ====================

    public function testToTree(): void
    {
        $data = [
            ['id' => 1, 'parent_id' => 0, 'name' => 'Root'],
            ['id' => 2, 'parent_id' => 1, 'name' => 'Child 1'],
            ['id' => 3, 'parent_id' => 1, 'name' => 'Child 2'],
            ['id' => 4, 'parent_id' => 2, 'name' => 'Grandchild'],
        ];

        $tree = ArrayHelper::toTree($data);
        $this->assertCount(1, $tree);
        $this->assertEquals('Root', $tree[0]['name']);
        $this->assertCount(2, $tree[0]['children']);
        $this->assertCount(1, $tree[0]['children'][0]['children']);
    }

    public function testToTreeEmpty(): void
    {
        $tree = ArrayHelper::toTree([]);
        $this->assertEmpty($tree);
    }

    public function testTreeToArray(): void
    {
        $tree = [
            [
                'id' => 1, 'name' => 'Root', 'children' => [
                    ['id' => 2, 'name' => 'Child 1', 'children' => []],
                    ['id' => 3, 'name' => 'Child 2', 'children' => []],
                ],
            ],
        ];

        $flat = ArrayHelper::treeToArray($tree);
        $this->assertCount(3, $flat);
        $this->assertEquals('Root', $flat[0]['name']);
        $this->assertEquals('Child 1', $flat[1]['name']);
        $this->assertEquals('Child 2', $flat[2]['name']);
    }

    public function testGroupBy(): void
    {
        $data = [
            ['name' => 'Alice', 'dept' => 'Engineering'],
            ['name' => 'Bob', 'dept' => 'Marketing'],
            ['name' => 'Charlie', 'dept' => 'Engineering'],
        ];

        $grouped = ArrayHelper::groupBy($data, 'dept');
        $this->assertCount(2, $grouped);
        $this->assertCount(2, $grouped['Engineering']);
        $this->assertCount(1, $grouped['Marketing']);
    }

    public function testSortBy(): void
    {
        $data = [
            ['name' => 'Charlie', 'age' => 30],
            ['name' => 'Alice', 'age' => 25],
            ['name' => 'Bob', 'age' => 28],
        ];

        $sorted = ArrayHelper::sortBy($data, 'age');
        $this->assertEquals('Alice', $sorted[0]['name']);
        $this->assertEquals('Bob', $sorted[1]['name']);
        $this->assertEquals('Charlie', $sorted[2]['name']);
    }

    public function testSortByDescending(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 25],
            ['name' => 'Charlie', 'age' => 30],
            ['name' => 'Bob', 'age' => 28],
        ];

        $sorted = ArrayHelper::sortBy($data, 'age', true);
        $this->assertEquals('Charlie', $sorted[0]['name']);
    }

    public function testUnique(): void
    {
        $data = [1, 2, 2, 3, 3, 3];
        $result = ArrayHelper::unique($data);
        $this->assertCount(3, $result);
    }

    public function testUniqueByKey(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 1, 'name' => 'Alice Duplicate'],
        ];

        $result = ArrayHelper::unique($data, 'id');
        $this->assertCount(2, $result);
    }

    public function testPaginate(): void
    {
        $data = range(1, 50);

        $result = ArrayHelper::paginate($data, 1, 10);
        $this->assertCount(10, $result['list']);
        $this->assertEquals(50, $result['pagination']['total']);
        $this->assertEquals(1, $result['pagination']['current_page']);
        $this->assertEquals(10, $result['pagination']['per_page']);
        $this->assertEquals(5, $result['pagination']['last_page']);
    }

    public function testPaginateSecondPage(): void
    {
        $data = range(1, 25);

        $result = ArrayHelper::paginate($data, 2, 10);
        $this->assertCount(10, $result['list']);
        $this->assertEquals(11, $result['list'][0]);
    }

    public function testMergeRecursive(): void
    {
        $array1 = ['a' => 1, 'b' => ['x' => 1, 'y' => 2]];
        $array2 = ['b' => ['y' => 3, 'z' => 4], 'c' => 5];

        $result = ArrayHelper::mergeRecursive($array1, $array2);
        $this->assertEquals(1, $result['a']);
        $this->assertEquals(1, $result['b']['x']);
        $this->assertEquals(3, $result['b']['y']); // overwritten
        $this->assertEquals(4, $result['b']['z']); // added
        $this->assertEquals(5, $result['c']);       // added
    }

    // ==================== DateHelper ====================

    public function testFormat(): void
    {
        $timestamp = strtotime('2025-03-14 10:30:00');
        $this->assertEquals('2025-03-14 10:30:00', DateHelper::format($timestamp));
        $this->assertEquals('2025-03-14', DateHelper::format($timestamp, 'Y-m-d'));
    }

    public function testFormatFromString(): void
    {
        $this->assertEquals('2025-03-14', DateHelper::format('2025-03-14 10:30:00', 'Y-m-d'));
    }

    public function testValidateDate(): void
    {
        $this->assertTrue(DateHelper::validate('2025-03-14'));
        $this->assertTrue(DateHelper::validate('2025-12-31'));
        $this->assertFalse(DateHelper::validate('2025-13-01'));
        $this->assertFalse(DateHelper::validate('not-a-date'));
    }

    public function testValidateDateWithFormat(): void
    {
        $this->assertTrue(DateHelper::validate('14/03/2025', 'd/m/Y'));
        $this->assertFalse(DateHelper::validate('2025-03-14', 'd/m/Y'));
    }

    public function testAge(): void
    {
        $birthday = date('Y-m-d', strtotime('-25 years'));
        $this->assertEquals(25, DateHelper::age($birthday));
    }

    public function testGetDateRangeToday(): void
    {
        $range = DateHelper::getDateRange('today');
        $this->assertCount(2, $range);
        $this->assertStringContainsString(date('Y-m-d'), $range[0]);
        $this->assertStringContainsString('00:00:00', $range[0]);
        $this->assertStringContainsString('23:59:59', $range[1]);
    }

    public function testGetDateRangeYesterday(): void
    {
        $range = DateHelper::getDateRange('yesterday');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $this->assertStringContainsString($yesterday, $range[0]);
    }

    public function testGetDateRangeMonth(): void
    {
        $range = DateHelper::getDateRange('month');
        $this->assertStringContainsString(date('Y-m-01'), $range[0]);
        $this->assertStringContainsString(date('Y-m-t'), $range[1]);
    }

    public function testGetDateRangeDefault(): void
    {
        // Unknown type falls back to today
        $range = DateHelper::getDateRange('unknown');
        $this->assertStringContainsString(date('Y-m-d'), $range[0]);
    }

    public function testIsWeekday(): void
    {
        // Monday
        $monday = strtotime('next Monday');
        $this->assertTrue(DateHelper::isWeekday($monday));
    }

    public function testIsWeekend(): void
    {
        // Saturday
        $saturday = strtotime('next Saturday');
        $this->assertTrue(DateHelper::isWeekend($saturday));
    }

    public function testIsWeekdayFromString(): void
    {
        $this->assertTrue(DateHelper::isWeekday('2025-03-10')); // Monday
        $this->assertFalse(DateHelper::isWeekday('2025-03-15')); // Saturday
    }

    public function testDayOfWeek(): void
    {
        $result = DateHelper::dayOfWeek('2025-03-10'); // Monday
        $this->assertEquals('星期一', $result);
    }
}
