<?php
declare(strict_types=1);

namespace core\ai\checks;

/**
 * 禁旧时间戳字段名（error）、代码截断标记（error）、未填占位（warning）。
 */
class ForbiddenPatternsCheck implements CheckInterface
{
    public function name(): string
    {
        return 'forbidden_patterns';
    }

    public function check(CheckContext $ctx): array
    {
        $results = [];
        foreach ($ctx->manifest['files'] ?? [] as $f) {
            $rel = (string) ($f['path'] ?? '');
            $abs = $ctx->filesDir() . '/' . $rel;
            if (!is_file($abs)) {
                continue;
            }
            $code = (string) file_get_contents($abs);

            if (preg_match('/\b(create_time|update_time|delete_time)\b/', $code)) {
                $results[] = new CheckResult($this->name(), 'error', "使用了旧时间戳字段名（应为 created_at/updated_at/deleted_at）：{$rel}", $rel);
            }
            if (preg_match('/\/\/\s*\.\.\.(\s|$)/', $code)) {
                $results[] = new CheckResult($this->name(), 'error', "存在代码截断标记 // ...：{$rel}", $rel);
            }
            if (str_contains($code, 'TODO: 补充选项')) {
                $results[] = new CheckResult($this->name(), 'warning', "存在未填占位（TODO: 补充选项）：{$rel}", $rel);
            }
        }
        return $results;
    }
}
