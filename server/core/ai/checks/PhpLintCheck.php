<?php
declare(strict_types=1);

namespace core\ai\checks;

/**
 * 对 stage 里每个 .php 跑 php -l，语法错为 error。
 */
class PhpLintCheck implements CheckInterface
{
    public function name(): string
    {
        return 'php_lint';
    }

    public function check(CheckContext $ctx): array
    {
        $results = [];
        foreach ($ctx->manifest['files'] ?? [] as $f) {
            $rel = (string) ($f['path'] ?? '');
            if (!str_ends_with($rel, '.php')) {
                continue;
            }
            $abs = $ctx->filesDir() . '/' . $rel;
            if (!is_file($abs)) {
                $results[] = new CheckResult($this->name(), 'error', "文件缺失：{$rel}", $rel);
                continue;
            }
            $output = [];
            $code = 0;
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($abs) . ' 2>&1', $output, $code);
            if ($code !== 0) {
                $results[] = new CheckResult($this->name(), 'error', "语法错误：{$rel}：" . trim(implode(' ', $output)), $rel);
            }
        }
        return $results;
    }
}
