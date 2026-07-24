<?php
declare(strict_types=1);

namespace core\ai\checks;

/**
 * namespace 与目录一致；路由文件用 v1.{module}.XxxController/方法 引用 + 中间件 + 分组名。
 */
class PathConventionCheck implements CheckInterface
{
    public function name(): string
    {
        return 'path_convention';
    }

    public function check(CheckContext $ctx): array
    {
        $results = [];

        foreach ($ctx->manifest['files'] ?? [] as $f) {
            $rel = (string) ($f['path'] ?? '');
            // 只校验 app/ 下、非路由文件的 php 命名空间
            if (!str_ends_with($rel, '.php') || !str_starts_with($rel, 'app/') || str_contains($rel, '/route/')) {
                continue;
            }
            $abs = $ctx->filesDir() . '/' . $rel;
            if (!is_file($abs)) {
                continue;
            }
            $expectedNs = str_replace('/', '\\', dirname($rel)); // app/model/widget => app\model\widget
            $code = (string) file_get_contents($abs);
            if (!preg_match('/namespace\s+' . preg_quote($expectedNs, '/') . '\s*;/', $code)) {
                $results[] = new CheckResult($this->name(), 'error', "命名空间与目录不一致，期望 namespace {$expectedNs}；文件：{$rel}", $rel);
            }
        }

        // 路由文件校验
        $module = (string) ($ctx->entities[0]['module'] ?? ($ctx->spec['module']['name'] ?? ''));
        if ($module !== '') {
            $routeRel = "app/adminapi/route/{$module}.php";
            $routeAbs = $ctx->filesDir() . '/' . $routeRel;
            if (is_file($routeAbs)) {
                $route = (string) file_get_contents($routeAbs);
                if (!str_contains($route, "->middleware(['admin_auth', 'admin_permission', 'admin_log'])")) {
                    $results[] = new CheckResult($this->name(), 'error', "路由文件缺少标准中间件：{$routeRel}", $routeRel);
                }
                foreach ($ctx->entities as $e) {
                    $group = (string) ($e['route_group'] ?? '');
                    $model = (string) ($e['model'] ?? '');
                    if ($group !== '' && !str_contains($route, "Route::group('{$group}'")) {
                        $results[] = new CheckResult($this->name(), 'error', "路由缺少分组 {$group}：{$routeRel}", $group);
                    }
                    if ($model !== '' && !str_contains($route, "v1.{$module}.{$model}Controller/")) {
                        $results[] = new CheckResult($this->name(), 'error', "路由控制器引用格式不符：v1.{$module}.{$model}Controller", $model);
                    }
                }
            }
        }
        return $results;
    }
}
