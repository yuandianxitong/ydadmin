<?php
declare(strict_types=1);

namespace core\ai;

class FileWriter
{
    protected array $skipped = [];

    public function __construct(protected string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
    }

    public static function isSafeRelPath(string $path): bool
    {
        // 先归一化反斜杠为正斜杠
        $path = str_replace('\\', '/', $path);

        // 空串、null字符、以/开头均不安全
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0")) {
            return false;
        }

        // Windows 绝对路径（C:/ D:/ 等）不安全
        if (preg_match('/^[A-Za-z]:\//', $path)) {
            return false;
        }

        // 路径段中包含.. 则不安全
        foreach (explode('/', $path) as $seg) {
            if ($seg === '..') {
                return false;
            }
        }

        return true;
    }

    public function stageToTemp(array $files): string
    {
        $temp = $this->projectRoot . '/runtime/ai/' . date('Ymd-His') . '-' . substr(uniqid(), -4);
        foreach ($files as $file) {
            if (!self::isSafeRelPath($file['path'])) {
                $this->skipped[] = $file['path'];
                continue;
            }
            $target = $temp . '/' . $file['path'];
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            file_put_contents($target, $file['code']);
        }
        return $temp;
    }

    public function commit(string $tempDir, array $files): array
    {
        $written = [];
        foreach ($files as $file) {
            $src = $tempDir . '/' . $file['path'];
            if (!self::isSafeRelPath($file['path']) || !is_file($src)) {
                continue;
            }
            $target = $this->projectRoot . '/' . $file['path'];
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            copy($src, $target);
            $written[] = $file['path'];
        }
        return $written;
    }

    public function cleanupStale(int $hours = 24): void
    {
        $base = $this->projectRoot . '/runtime/ai';
        if (!is_dir($base)) {
            return;
        }
        foreach ((array) glob($base . '/*', GLOB_ONLYDIR) as $dir) {
            if (filemtime($dir) < time() - $hours * 3600) {
                $this->removeDir($dir);
            }
        }
    }

    public function getSkipped(): array
    {
        return $this->skipped;
    }

    protected function removeDir(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
