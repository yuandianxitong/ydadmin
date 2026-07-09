<?php
declare(strict_types=1);

namespace core\ai;

class DiffPreview
{
    public function __construct(protected string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
    }

    public function render(string $tempDir, array $files): string
    {
        $out = [];
        foreach ($files as $file) {
            $rel = $file['path'];
            $new = $tempDir . '/' . $rel;
            if (!is_file($new)) {
                continue; // 被安全校验跳过的文件
            }
            $old = $this->projectRoot . '/' . $rel;
            if (is_file($old)) {
                $out[] = $this->gitDiff($old, $new) ?? "[覆盖] {$rel}（git 不可用，无法展示差异）";
            } else {
                $lines = substr_count($file['code'], "\n") + 1;
                $out[] = "[新增] {$rel}（{$lines} 行）";
            }
        }
        return implode("\n", $out);
    }

    protected function gitDiff(string $old, string $new): ?string
    {
        $cmd = 'git diff --no-index --color ' . escapeshellarg($old) . ' ' . escapeshellarg($new) . ' 2>/dev/null';
        exec($cmd, $lines, $code);
        // git diff --no-index：有差异退出码 1，无差异 0；127 = git 不存在
        if ($code > 1) {
            return null;
        }
        return $code === 0 ? "[无变化] {$new}" : implode("\n", $lines);
    }
}
