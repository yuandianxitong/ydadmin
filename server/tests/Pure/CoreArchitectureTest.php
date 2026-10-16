<?php

declare(strict_types=1);

namespace tests\Pure;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CoreArchitectureTest extends TestCase
{
    /** @return array<string, array{string, string, int, string}> */
    public static function boundaries(): array
    {
        return [
            'generator consumes support' => ['generator/Example.php', '<?php namespace core\generator; use core\support\PathGuard;', 0, '检查通过'],
            'support cannot consume generator' => ['support/Example.php', '<?php namespace core\support; use core\generator\ArtifactWriter;', 1, '禁止依赖 core\generator'],
            'response cannot consume documentation' => ['response/Example.php', '<?php namespace core\response; function schema() { return \core\apidoc\OpenApiDocument::class; }', 1, '禁止依赖 core\apidoc'],
            'import cannot consume app' => ['config/Example.php', '<?php namespace core\config; use app\repository\system\SystemConfigRepository;', 1, '核心不得依赖应用层'],
            'fully qualified name cannot consume app' => ['config/Example.php', '<?php namespace core\config; function repository() { return new \app\repository\system\SystemConfigRepository(); }', 1, '核心不得依赖应用层'],
            'unknown directory' => ['helper/Example.php', '<?php namespace core\helper;', 1, '未定义的核心能力目录'],
            'namespace follows directory' => ['config/Example.php', '<?php namespace core\response;', 1, '命名空间必须为 core\config'],
            'comments do not create dependencies' => ['config/Example.php', '<?php namespace core\config; /* use app\repository\Example; */', 0, '检查通过'],
            'bootstrap exception does not apply to tools' => ['support/Example.php', '<?php namespace core\support; use core\validation\ValidatorFactory;', 1, '禁止依赖 core\validation'],
            'templates are not runtime code' => ['generator/stubs/example.stub.php', '<?php use app\model\Example; class <?= $model ?>', 0, '检查通过'],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_architecture_boundaries(string $path, string $source, int $expectedStatus, string $expectedOutput): void
    {
        $root = sys_get_temp_dir() . '/yd-core-' . bin2hex(random_bytes(8));
        $file = $root . '/' . $path;
        mkdir(dirname($file), 0o700, true);
        file_put_contents($file, $source);
        try {
            $command = escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg(dirname(__DIR__, 2) . '/scripts/check-core-architecture.php') . ' '
                . escapeshellarg($root);
            exec($command . ' 2>&1', $output, $status);
            $this->assertSame($expectedStatus, $status, implode("\n", $output));
            $this->assertStringContainsString($expectedOutput, implode("\n", $output));
        } finally {
            unlink($file);
            $directory = dirname($file);
            while ($directory !== $root) {
                rmdir($directory);
                $directory = dirname($directory);
            }
            rmdir($root);
        }
    }
}
