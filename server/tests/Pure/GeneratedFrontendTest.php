<?php

declare(strict_types=1);

namespace tests\Pure;

use core\generator\GeneratorRequest;
use core\generator\ModuleBlueprint;
use core\generator\TableDefinition;
use core\generator\TemplateRenderer;
use core\generator\TypeInference;
use PHPUnit\Framework\TestCase;
use tests\Unit\Generator\GenArticleFixtureColumns;

final class GeneratedFrontendTest extends TestCase
{
    use GenArticleFixtureColumns;

    public function test_generated_vue_and_api_files_pass_vue_tsc(): void
    {
        $server = dirname(__DIR__, 2);
        $admin = dirname($server) . '/admin';
        $checker = $admin . '/node_modules/.bin/vue-tsc';
        if (!is_file($checker)) {
            self::markTestSkipped('需要先安装 admin/ 的前端依赖');
        }
        $root = sys_get_temp_dir() . '/generated-frontend-' . bin2hex(random_bytes(6));
        mkdir($root);
        symlink($admin . '/node_modules', $root . '/node_modules');
        try {
            $inference = new TypeInference();
            $columns = self::genArticleColumns();
            foreach (['event_date' => 'date', 'settings' => 'json', 'password' => 'varchar(255)'] as $name => $type) {
                $columns[] = $inference->describe(['Field' => $name, 'Type' => $type, 'Null' => 'YES']);
            }
            $columns[] = $inference->describe(['Field' => 'password_hash', 'Type' => 'varchar(255)', 'Null' => 'NO', 'Default' => '']);
            $columns[] = $inference->describe(['Field' => 'category_id', 'Type' => 'int', 'Null' => 'NO'])
                ->withOverrides('select', true, true, true, [['label' => '分类一', 'value' => '1']]);
            $blueprint = new ModuleBlueprint(new TableDefinition('gen_articles', '测试', $columns), new GeneratorRequest('gen_articles', 'demo', 'GenArticle', '', []));
            $renderer = new TemplateRenderer($server . '/core/generator/stubs');
            foreach ($blueprint->artifacts() as $artifact) {
                if ($artifact['base'] !== 'repo') {
                    continue;
                }
                $path = $root . '/' . $artifact['path'];
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0o755, true);
                }
                file_put_contents($path, $renderer->render($artifact['stub'], $artifact['vars']));
            }
            file_put_contents($root . '/tsconfig.json', json_encode([
                'extends' => $admin . '/tsconfig.json',
                'compilerOptions' => ['baseUrl' => $root, 'paths' => [
                    '@/api/demo/*' => [$root . '/admin/src/api/demo/*'], '@/*' => [$admin . '/src/*'],
                ]],
                'include' => [$root . '/admin/src/**/*.ts', $root . '/admin/src/**/*.vue', $admin . '/src/types/**/*.d.ts', $admin . '/*.d.ts'],
            ], JSON_THROW_ON_ERROR));
            exec(escapeshellarg($checker) . ' --noEmit -p ' . escapeshellarg($root . '/tsconfig.json') . ' 2>&1', $output, $code);
            self::assertSame(0, $code, implode("\n", $output));
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($root);
        }
    }
}
