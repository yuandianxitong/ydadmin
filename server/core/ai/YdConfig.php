<?php
// server/core/ai/YdConfig.php
declare(strict_types=1);

namespace core\ai;

class YdConfig
{
    protected string $file;

    /** 旧配置目录（~/.ydadmin），仅作只读回退；写入一律走新目录并整体迁移 */
    protected string $legacyFile;

    public function __construct(?string $homeDir = null)
    {
        $home = $homeDir ?: (getenv('HOME') ?: sys_get_temp_dir());
        $this->file = $home . '/.ydsaas/config.json';
        $this->legacyFile = $home . '/.ydadmin/config.json';
    }

    protected function readData(): array
    {
        $source = is_file($this->file) ? $this->file : (is_file($this->legacyFile) ? $this->legacyFile : null);
        if ($source === null) {
            return [];
        }
        return json_decode((string) file_get_contents($source), true) ?: [];
    }

    public function get(string $key): mixed
    {
        $data = $this->readData();
        return $data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $data = $this->readData();
        if ($value === null) {
            unset($data[$key]);
        } else {
            $data[$key] = $value;
        }
        if (!is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0700, true);
        }
        file_put_contents($this->file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        chmod($this->file, 0600);
    }

    public function path(): string
    {
        return $this->file;
    }
}
