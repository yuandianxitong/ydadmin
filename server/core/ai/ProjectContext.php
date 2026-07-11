<?php
declare(strict_types=1);

namespace core\ai;

/**
 * 项目唯一标识：首次生成后持久化在 {home}/.ydsaas/projects.json（键为项目根路径）
 * 兼容旧目录 {home}/.ydadmin/projects.json（只读回退，新写入一律进新目录）
 */
class ProjectContext
{
    protected string $home;

    public function __construct(?string $homeDir = null)
    {
        $this->home = $homeDir ?: (getenv('HOME') ?: sys_get_temp_dir());
    }

    public function id(): string
    {
        $file = $this->home . '/.ydsaas/projects.json';
        $legacyFile = $this->home . '/.ydadmin/projects.json';
        $rootKey = root_path();
        $source = is_file($file) ? $file : (is_file($legacyFile) ? $legacyFile : null);
        $map = $source !== null ? (json_decode((string) file_get_contents($source), true) ?: []) : [];
        if (!isset($map[$rootKey])) {
            $map[$rootKey] = 'proj_' . bin2hex(random_bytes(8));
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0700, true);
            }
            file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        return $map[$rootKey];
    }
}
