<?php

declare(strict_types=1);

namespace tests\Unit\Storage;

use core\storage\driver\LocalDriver;
use RuntimeException;
use tests\TestCase;

/**
 * 本地驱动：落盘、取 URL、删除、存在性判断。所有落盘都发生在 public/storage/unit-test/ 下，
 * tearDown 自行清理——`composer contract` 收尾会检查 public/storage 没有遗留测试文件。
 */
final class LocalDriverTest extends TestCase
{
    private LocalDriver $driver;

    /** @var list<string> 本用例落盘的相对路径 */
    private array $createdRelativePaths = [];

    /** @var list<string> 本用例建的临时源文件（正常 put() 后源文件会被移走，这里只兜底） */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new LocalDriver();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdRelativePaths as $relativePath) {
            $absolute = $this->publicStoragePath($relativePath);
            if (is_file($absolute)) {
                unlink($absolute);
            }
            @rmdir(dirname($absolute));
        }
        foreach ($this->tmpFiles as $tmp) {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
        parent::tearDown();
    }

    private function publicStoragePath(string $relativePath): string
    {
        return public_path('storage') . '/' . ltrim($relativePath, '/');
    }

    private function makeTmpFile(string $content = 'hello'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'storage_test_');
        if ($path === false) {
            throw new RuntimeException('无法创建临时文件');
        }
        file_put_contents($path, $content);
        $this->tmpFiles[] = $path;

        return $path;
    }

    private function relative(string $prefix): string
    {
        $relative = 'unit-test/' . uniqid($prefix, true) . '.txt';
        $this->createdRelativePaths[] = $relative;

        return $relative;
    }

    public function test_put_writes_into_public_storage_and_creates_missing_directory(): void
    {
        $tmp = $this->makeTmpFile('hello world');
        $relative = $this->relative('file_');
        $targetDir = dirname($this->publicStoragePath($relative));
        $this->assertDirectoryDoesNotExist($targetDir, '前置条件：目标目录事先不存在');

        $this->driver->put($tmp, $relative);

        $this->assertFileExists($this->publicStoragePath($relative));
        $this->assertSame('hello world', file_get_contents($this->publicStoragePath($relative)));
    }

    public function test_put_consumes_the_source_temp_file(): void
    {
        $tmp = $this->makeTmpFile('x');
        $relative = $this->relative('moved_');

        $this->driver->put($tmp, $relative);

        // 契约：put() 之后源临时文件已不存在，调用方必须在 put() 之前取 size（Task 5 的红线之一）
        $this->assertFileDoesNotExist($tmp);
    }

    public function test_put_throws_when_source_file_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->driver->put(sys_get_temp_dir() . '/not-exists-' . uniqid(), $this->relative('missing_'));
    }

    public function test_get_url_is_relative_and_normalized(): void
    {
        $this->assertSame('/storage/a/b.png', $this->driver->getUrl('a/b.png'));
        $this->assertSame('/storage/a/b.png', $this->driver->getUrl('/a/b.png'));
        $this->assertSame('/storage/a/b.png', $this->driver->getUrl('a\\b.png'), 'Windows 反斜杠要归一化');
    }

    public function test_exists_is_false_before_put_and_true_after(): void
    {
        $relative = $this->relative('exists_');
        $this->assertFalse($this->driver->exists($relative));

        $this->driver->put($this->makeTmpFile('x'), $relative);

        $this->assertTrue($this->driver->exists($relative));
    }

    public function test_delete_removes_file_and_is_idempotent(): void
    {
        $relative = $this->relative('del_');
        $this->driver->put($this->makeTmpFile('x'), $relative);

        $this->assertTrue($this->driver->delete($relative));
        $this->assertFalse($this->driver->exists($relative));
        $this->assertFalse($this->driver->delete($relative), '文件不存在时返回 false，不抛异常');
    }

    /**
     * `..` 路径段一律拒绝。put/delete/exists 共用 absolutePath()，三个入口逐一断言——
     * 真正危险的是 delete()：越出存储根目录的 unlink() 删的是别人的文件。
     */
    public function test_parent_directory_segments_are_rejected_on_every_entry_point(): void
    {
        $tmp = $this->makeTmpFile('x');

        foreach (['../escaped.txt', 'unit-test/../../escaped.txt', 'a/../b.txt', '..', '/../escaped.txt', 'a\\..\\b.txt'] as $evil) {
            $this->assertRejected(fn () => $this->driver->put($tmp, $evil), "put({$evil})");
            $this->assertRejected(fn () => $this->driver->delete($evil), "delete({$evil})");
            $this->assertRejected(fn () => $this->driver->exists($evil), "exists({$evil})");
        }

        $this->assertFileExists($tmp, '被拒的 put() 不该动源文件');
    }

    /** `foo..bar.png` 含 `..` 子串但不含 `..` 路径段，是合法文件名，不得被误拒。 */
    public function test_filename_containing_dot_dot_as_a_substring_is_not_rejected(): void
    {
        $relative = 'unit-test/' . uniqid('foo..bar_', true) . '..png';
        $this->createdRelativePaths[] = $relative;

        $this->driver->put($this->makeTmpFile('substring'), $relative);

        $this->assertSame('substring', file_get_contents($this->publicStoragePath($relative)));
        $this->assertTrue($this->driver->exists($relative));
        $this->assertTrue($this->driver->delete($relative));
    }

    /**
     * 先接住异常再断言，不用 try/catch 包 $this->fail()：PHPUnit 的 AssertionFailedError
     * 继承自 \RuntimeException，catch (RuntimeException) 会把 fail() 本身一起吞掉。
     */
    private function assertRejected(callable $call, string $label): void
    {
        $thrown = null;
        try {
            $call();
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, "{$label}：含 .. 路径段必须抛 RuntimeException");
        $this->assertStringContainsString('..', $thrown->getMessage(), $label);
    }
}
