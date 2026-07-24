<?php
declare(strict_types=1);

namespace core\ai\checks;

/**
 * 隔离子进程加载路由文件：用记录型 Route 桩 require 路由文件，
 * 校验无 fatal/parse error、注册了预期分组、挂了标准中间件。
 */
class RouteLoadingCheck implements CheckInterface
{
    public function name(): string
    {
        return 'route_loading';
    }

    public function check(CheckContext $ctx): array
    {
        $module = (string) ($ctx->entities[0]['module'] ?? ($ctx->spec['module']['name'] ?? ''));
        $routeRel = "app/adminapi/route/{$module}.php";
        $routeFile = $ctx->filesDir() . '/' . $routeRel;
        if (!is_file($routeFile)) {
            return [new CheckResult($this->name(), 'error', "路由文件缺失：{$routeRel}", $routeRel)];
        }

        $harness = $this->writeHarness();
        try {
            $output = [];
            $code = 0;
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($harness) . ' ' . escapeshellarg($routeFile) . ' 2>&1', $output, $code);
            $raw = trim(implode("\n", $output));
            if ($code !== 0) {
                return [new CheckResult($this->name(), 'error', "路由文件加载失败：{$raw}", $routeRel)];
            }
            $groups = json_decode($raw, true);
            if (!is_array($groups)) {
                return [new CheckResult($this->name(), 'error', "路由加载输出异常：{$raw}", $routeRel)];
            }
            $byName = [];
            foreach ($groups as $g) {
                $byName[(string) ($g['name'] ?? '')] = $g['mw'] ?? [];
            }
            $results = [];
            foreach ($ctx->entities as $e) {
                $grp = (string) ($e['route_group'] ?? '');
                if ($grp === '') {
                    continue;
                }
                if (!array_key_exists($grp, $byName)) {
                    $results[] = new CheckResult($this->name(), 'error', "缺少路由分组：{$grp}", $grp);
                    continue;
                }
                foreach (['admin_auth', 'admin_permission', 'admin_log'] as $mw) {
                    if (!in_array($mw, (array) $byName[$grp], true)) {
                        $results[] = new CheckResult($this->name(), 'error', "分组 {$grp} 缺少中间件 {$mw}", $grp);
                    }
                }
            }
            return $results;
        } finally {
            @unlink($harness);
        }
    }

    private function writeHarness(): string
    {
        $script = <<<'PHP'
<?php
namespace think\facade {
    class Route {
        public static array $groups = [];
        public static function group($name, $cb = null) {
            self::$groups[] = ['name' => $name, 'mw' => []];
            if (is_callable($cb)) { $cb(); }
            return new class {
                public function middleware($mw) {
                    $i = count(\think\facade\Route::$groups) - 1;
                    if ($i >= 0) { \think\facade\Route::$groups[$i]['mw'] = $mw; }
                    return $this;
                }
                public function __call($n, $a) { return $this; }
            };
        }
        public static function __callStatic($n, $a) {
            return new class { public function __call($n, $a) { return $this; } };
        }
    }
}
namespace {
    require $argv[1];
    echo json_encode(\think\facade\Route::$groups);
}
PHP;
        $tmp = tempnam(sys_get_temp_dir(), 'ydroute_') . '.php';
        file_put_contents($tmp, $script);
        return $tmp;
    }
}
