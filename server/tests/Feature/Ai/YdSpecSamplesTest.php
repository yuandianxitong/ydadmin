<?php
// server/tests/Feature/Ai/YdSpecSamplesTest.php
declare(strict_types=1);

namespace tests\Feature\Ai;

use core\ai\ydspec\YdSpecValidator;
use tests\TestCase;

class YdSpecSamplesTest extends TestCase
{
    private static function fixtureDir(): string
    {
        return dirname(__DIR__, 2) . '/fixtures/ydspec';
    }

    private static function loadSpec(string $file): array
    {
        return (array) json_decode((string) file_get_contents($file), true);
    }

    /**
     * "Good" fixtures: every *.json directly under fixtures/ydspec must pass
     * structural validation with zero errors AND produce no error-severity
     * semantic issues.
     */
    public static function goodSampleProvider(): array
    {
        $cases = [];
        foreach (glob(self::fixtureDir() . '/*.json') as $file) {
            $cases[basename($file)] = [$file];
        }
        return $cases;
    }

    /**
     * @dataProvider goodSampleProvider
     */
    public function testGoodSampleHasNoStructuralOrBlockingSemanticIssues(string $file): void
    {
        $spec = self::loadSpec($file);
        $validator = new YdSpecValidator();

        $this->assertSame([], $validator->validateStructure($spec), "结构校验失败：{$file}");

        $blocking = array_filter(
            $validator->validateSemantics($spec),
            static fn (array $i): bool => $i['severity'] === 'error'
        );
        $this->assertSame([], array_values($blocking), "语义阻断项：{$file}");
    }

    /**
     * "Bad" semantic fixtures: each is structurally valid but must be rejected
     * by one SPECIFIC semantic rule (error severity).
     */
    public static function badSemanticProvider(): array
    {
        return [
            'reserved-field'   => ['bad/reserved_field.json', 'reserved-field'],
            'money-decimal'    => ['bad/money_decimal.json', 'money-decimal'],
            'relation-target'  => ['bad/relation_target.json', 'relation-target'],
            'log-append-only'  => ['bad/log_append_only.json', 'log-append-only'],
        ];
    }

    /**
     * @dataProvider badSemanticProvider
     */
    public function testBadSemanticSampleTriggersSpecificRule(string $relative, string $expectedRule): void
    {
        $spec = self::loadSpec(self::fixtureDir() . '/' . $relative);
        $validator = new YdSpecValidator();

        // Pure semantic rejection: structure must be valid so the failure is
        // attributable to the semantic rule, not to a malformed document.
        $this->assertSame([], $validator->validateStructure($spec), "样例应结构合法：{$relative}");

        $errorRules = array_column(
            array_filter(
                $validator->validateSemantics($spec),
                static fn (array $i): bool => $i['severity'] === 'error'
            ),
            'rule'
        );
        $this->assertContains(
            $expectedRule,
            $errorRules,
            "样例 {$relative} 未触发预期语义规则 {$expectedRule}，实际错误规则：" . implode(',', $errorRules)
        );
    }

    /**
     * "Bad" structural fixtures: each must be rejected by structural validation,
     * with an error message referencing the SPECIFIC offending property.
     */
    public static function badStructuralProvider(): array
    {
        return [
            'missing-module'      => ['bad/missing_module.json', 'module'],
            'decimal-no-precision' => ['bad/decimal_no_precision.json', 'precision'],
        ];
    }

    /**
     * @dataProvider badStructuralProvider
     */
    public function testBadStructuralSampleIsRejected(string $relative, string $needle): void
    {
        $spec = self::loadSpec(self::fixtureDir() . '/' . $relative);
        $errors = (new YdSpecValidator())->validateStructure($spec);

        $this->assertNotEmpty($errors, "样例应结构非法：{$relative}");
        $this->assertStringContainsStringIgnoringCase(
            $needle,
            implode(' | ', $errors),
            "样例 {$relative} 的结构错误未指向预期属性 {$needle}：" . implode(' | ', $errors)
        );
    }
}
