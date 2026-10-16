<?php

declare(strict_types=1);

namespace app\service\operations;

final class PrivateTaskFiles
{
    public function directory(): string
    {
        $directory = (string) config('operations.task_storage');
        if (!is_dir($directory) && !mkdir($directory, 0o700, true) && !is_dir($directory)) {
            throw new \RuntimeException('任务目录不可写');
        }
        $real = realpath($directory);
        $public = realpath(public_path());
        if ($real === false || ($public !== false && ($real === $public || str_starts_with($real, $public . DIRECTORY_SEPARATOR)))) {
            throw new \RuntimeException('任务文件必须保存在非公开目录');
        }

        return $real;
    }

    public function path(string $name): string
    {
        if (preg_match('/^[a-f0-9]{32}\.(csv|part)\z/', $name) !== 1) {
            throw new \InvalidArgumentException('非法任务文件名');
        }

        return $this->directory() . '/' . $name;
    }

    public function remove(string $name): void
    {
        $path = $this->path($name);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public static function csvCell(mixed $value): string
    {
        $text = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR);

        return preg_match('/^[\s]*[=+\-@]/u', $text) === 1 ? "'" . $text : $text;
    }
}
