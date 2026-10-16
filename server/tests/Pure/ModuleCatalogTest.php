<?php

declare(strict_types=1);

namespace tests\Pure;

use core\module\ModuleCatalog;
use core\module\ModuleDescriptor;
use PHPUnit\Framework\TestCase;

final class ModuleCatalogTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/yd-modules-' . bin2hex(random_bytes(5));
        mkdir($this->root . '/server/modules/demo', 0o755, true);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    private function descriptor(): array
    {
        return ['schema_version' => 1, 'id' => 'demo.item', 'module' => 'demo', 'model' => 'Item', 'title' => 'Item', 'table' => 'items', 'primary_key' => 'id',
            'permissions' => ['demo.item.list'], 'routes' => [['method' => 'GET', 'path' => '/adminapi/demo/item', 'action' => 'index', 'permission' => 'demo.item.list']],
            'fields' => [], 'capabilities' => [], 'artifacts' => ['controller' => 'server/app/adminapi/controller/demo/ItemController.php']];
    }

    public function test_bad_json_is_reported_without_hiding_valid_modules_and_missing_files_are_optional(): void
    {
        file_put_contents($this->root . '/server/modules/demo/item.json', (new ModuleDescriptor($this->descriptor()))->toJson());
        file_put_contents($this->root . '/server/modules/demo/bad.json', '{broken');
        $catalog = new ModuleCatalog();
        $result = $catalog->inspect($this->root . '/server', $this->root);
        $this->assertCount(1, $result['modules']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringNotContainsString($this->root, json_encode($result['errors']));
        $checked = $catalog->inspect($this->root . '/server', $this->root, true);
        $this->assertSame([], $checked['modules']);
        $this->assertCount(2, $checked['errors']);
    }

    public function test_parent_traversal_and_foreign_permission_references_are_rejected(): void
    {
        foreach (['path', 'permission'] as $case) {
            $data = $this->descriptor();
            if ($case === 'path') {
                $data['artifacts']['controller'] = 'server/app/../../.env';
            } else {
                $data['routes'][0]['permission'] = 'system.admin.delete';
            }
            try {
                new ModuleDescriptor($data);
                $this->fail('Unsafe module metadata must be rejected');
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_symbolic_link_module_descriptors_are_never_followed(): void
    {
        $outside = $this->root . '/outside.json';
        file_put_contents($outside, (new ModuleDescriptor($this->descriptor()))->toJson());
        symlink($outside, $this->root . '/server/modules/demo/item.json');
        $result = (new ModuleCatalog())->inspect($this->root . '/server', $this->root);
        $this->assertSame([], $result['modules']);
        $this->assertCount(1, $result['errors']);
    }
}
