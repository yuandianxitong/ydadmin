<?php
// server/core/ai/YdConfig.php
declare(strict_types=1);

namespace core\ai;

class YdConfig
{
    protected string $file;

    public function __construct(?string $homeDir = null)
    {
        $home = $homeDir ?: (getenv('HOME') ?: sys_get_temp_dir());
        $this->file = $home . '/.ydadmin/config.json';
    }

    public function get(string $key): mixed
    {
        if (!is_file($this->file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($this->file), true) ?: [];
        return $data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $data = is_file($this->file) ? (json_decode((string) file_get_contents($this->file), true) ?: []) : [];
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
