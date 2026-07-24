<?php
declare(strict_types=1);

namespace core\ai\checks;

/**
 * 静态扫描生成 PHP 的越层：
 * - Controller：禁 Db:: 与 Model 静态查询。
 * - Service：禁 Db::table/name/query/execute 与 Model 静态查询（Db::startTrans/commit/rollback 例外）。
 */
class LayerComplianceCheck implements CheckInterface
{
    public function name(): string
    {
        return 'layer_compliance';
    }

    public function check(CheckContext $ctx): array
    {
        $results = [];
        $modelStatic = '/\b[A-Z][A-Za-z0-9_]*::(where|find|create|update|destroy|insert|select)\s*\(/';

        foreach ($ctx->manifest['files'] ?? [] as $f) {
            $rel = (string) ($f['path'] ?? '');
            if (!str_ends_with($rel, '.php')) {
                continue;
            }
            $abs = $ctx->filesDir() . '/' . $rel;
            if (!is_file($abs)) {
                continue;
            }
            $code = (string) file_get_contents($abs);

            if (str_contains($rel, '/controller/')) {
                if (preg_match('/\bDb::/', $code) || preg_match($modelStatic, $code)) {
                    $results[] = new CheckResult($this->name(), 'error', "Controller 越层调用 Db/Model：{$rel}", $rel);
                }
                continue;
            }

            if (str_contains($rel, '/service/')) {
                // 去掉允许的事务调用后再检测
                $stripped = preg_replace('/\bDb::(startTrans|commit|rollback)\s*\(\s*\)/', '', $code);
                if (preg_match('/\bDb::(table|name|query|execute)\s*\(/', (string) $stripped) || preg_match($modelStatic, (string) $stripped)) {
                    $results[] = new CheckResult($this->name(), 'error', "Service 越层调用 Db/Model：{$rel}", $rel);
                }
            }
        }
        return $results;
    }
}
