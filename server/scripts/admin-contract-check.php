<?php

/**
 * 契约检查（spec §7.1）：对运行中的服务逐条断言响应格式与关键字段，随里程碑扩充。
 *
 * 用法：php webman db:reset --force（开发库首次或表结构变化后）→ php start.php start -d → php scripts/admin-contract-check.php
 * M1a 起：用 .env 配置的库临时建一个超管账号（contract_ 前缀），跑完删除；验证码从服务同一个 Redis 读取，校验照常执行。
 * M1b 起：覆盖配置、字典、日志、通知、仪表盘。改过的配置在退出时写回原值；日志的删除与清空只用一个
 *   「本部门」数据范围的临时账号执行，只会动到本次运行自己产生的日志；操作日志中间件为临时账号记下的日志随账号一起删除。
 * 地址：默认取 .env 的 SERVER_LISTEN 端口；可用环境变量 CONTRACT_BASE_URL 覆盖。
 * 退出码：0 = 全部通过。
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$port = parse_url((string) ($_ENV['SERVER_LISTEN'] ?? 'http://0.0.0.0:8000'), PHP_URL_PORT) ?: 8000;
$base = rtrim((string) (getenv('CONTRACT_BASE_URL') ?: "http://127.0.0.1:{$port}"), '/');

/** @var list<string> $failures */
$failures = [];
$passes = 0;

/**
 * @param list<string> $headers
 * @param array<string, mixed>|null $json 非 null 时以 JSON 请求体发送
 * @return array{status: int, headers: array<string, string>, body: string, json: mixed}
 */
function http(string $method, string $url, array $headers = [], ?array $json = null): array
{
    $responseHeaders = [];
    $ch = curl_init($url);
    if ($json !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($json, JSON_UNESCAPED_UNICODE));
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($line);
        },
    ]);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return ['status' => $status, 'headers' => $responseHeaders, 'body' => $body, 'json' => json_decode($body, true)];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $passes;
    if ($ok) {
        $passes++;
        echo "  [PASS] {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "  [FAIL] {$label}" . ($detail !== '' ? " :: {$detail}" : '') . "\n";
}

function isEnvelope(mixed $json): bool
{
    return is_array($json)
        && array_keys($json) === ['code', 'message', 'data', 'timestamp']
        && is_int($json['code'])
        && is_string($json['message'])
        && is_int($json['timestamp']);
}

$api = ['Accept: application/json'];

echo "\n=== 健康检查 ===\n";
$r = http('GET', "{$base}/adminapi/health", $api);
check('HTTP 200', $r['status'] === 200, (string) $r['status']);
check('响应格式为 {code,message,data,timestamp}', isEnvelope($r['json']), $r['body']);
check('code = 200', ($r['json']['code'] ?? null) === 200);
check('data.status = ok', ($r['json']['data']['status'] ?? null) === 'ok');
check('data.version 为字符串', is_string($r['json']['data']['version'] ?? null));

echo "\n=== trace-id ===\n";
$trace = 'trace_1726000000000_abc123def';
$r = http('GET', "{$base}/adminapi/health", [...$api, "X-Trace-Id: {$trace}"]);
check('合法的入站 X-Trace-Id 原样回写', ($r['headers']['x-trace-id'] ?? '') === $trace, json_encode($r['headers']));
$r = http('GET', "{$base}/adminapi/health", [...$api, 'X-Trace-Id: bad']);
check('非法的入站 X-Trace-Id 被替换为 32 位十六进制', preg_match('/^[0-9a-f]{32}$/', $r['headers']['x-trace-id'] ?? '') === 1);

echo "\n=== 未知接口 ===\n";
$r = http('GET', "{$base}/adminapi/does-not-exist", $api);
check('HTTP 404（未知路由，与 TP8 版一致）', $r['status'] === 404, (string) $r['status']);
check('响应格式正确且 code = 404', isEnvelope($r['json']) && $r['json']['code'] === 404, $r['body']);

echo "\n=== SPA 托管 ===\n";
$r = http('GET', "{$base}/admin/system/admin");
check('/admin 深层路由返回 index.html', $r['status'] === 200 && str_contains($r['headers']['content-type'] ?? '', 'text/html'));
$r = http('GET', "{$base}/admin/favicon.ico");
check('/admin 下的静态文件按原文件返回', $r['status'] === 200 && !str_contains($r['headers']['content-type'] ?? '', 'text/html'));
$r = http('GET', "{$base}/", ['User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Mobile']);
check('/ 在移动端 UA 下 302 到 /mobile/', $r['status'] === 302 && ($r['headers']['location'] ?? '') === '/mobile/');
if (!is_file(dirname(__DIR__) . '/public/mobile/index.html')) {
    $r = http('GET', "{$base}/mobile/");
    check('未部署的 mobile 返回 404', $r['status'] === 404);
}

// ---------------------------------------------------------------- M1a
// 加载 webman（配置、Eloquent、Redis），用服务端同一套逻辑建临时超管、读验证码
require_once dirname(__DIR__) . '/support/bootstrap.php';

$suffix = bin2hex(random_bytes(3));
$username = "contract_{$suffix}";
$password = 'Contract#2026';
$created = support\Container::get(app\service\system\AdminService::class)->createAdmin([
    'username' => $username,
    'email'    => "{$username}@contract.local",
    'password' => $password,
    'role_ids' => [1],
]);
$contractAdminId = (int) $created['id'];

// 本次运行创建的所有临时行，登记在这里；退出时（含任何异常/致命错误）统一硬删，含关联表。
// M1b 起：操作日志中间件会为契约账号的每个写请求记一条操作日志，按 admin_id 一并删除；改过的配置写回原值。
/** @var array{admin_ids: list<int>, role_ids: list<int>, menu_ids: list<int>, dept_ids: list<int>, dict_ids: list<int>, notification_ids: list<int>, configs: array<string, string>} $cleanup */
$cleanup = ['admin_ids' => [$contractAdminId], 'role_ids' => [], 'menu_ids' => [], 'dept_ids' => [], 'dict_ids' => [], 'notification_ids' => [], 'configs' => []];
register_shutdown_function(static function () use (&$cleanup): void {
    // 先删关联表（外键依赖方向），再删主表
    foreach ($cleanup['admin_ids'] as $id) {
        support\Db::table('admin_roles')->where('admin_id', $id)->delete();
        support\Db::table('admin_login_logs')->where('admin_id', $id)->delete();
        support\Db::table('admin_operation_logs')->where('admin_id', $id)->delete();
        support\Db::table('notification_reads')->where('admin_id', $id)->delete();
    }
    foreach ($cleanup['role_ids'] as $id) {
        support\Db::table('admin_roles')->where('role_id', $id)->delete();
        support\Db::table('role_menus')->where('role_id', $id)->delete();
        support\Db::table('role_departments')->where('role_id', $id)->delete();
    }
    foreach ($cleanup['menu_ids'] as $id) {
        support\Db::table('role_menus')->where('menu_id', $id)->delete();
    }
    foreach ($cleanup['dept_ids'] as $id) {
        support\Db::table('role_departments')->where('department_id', $id)->delete();
    }
    foreach ($cleanup['dict_ids'] as $id) {
        support\Db::table('dictionary_items')->where('dictionary_id', $id)->delete();
        support\Db::table('dictionaries')->where('id', $id)->delete();
    }
    foreach ($cleanup['notification_ids'] as $id) {
        support\Db::table('notification_reads')->where('notification_id', $id)->delete();
        support\Db::table('notifications')->where('id', $id)->delete();
    }
    foreach ($cleanup['admin_ids'] as $id) {
        support\Db::table('admins')->where('id', $id)->delete();
    }
    foreach ($cleanup['role_ids'] as $id) {
        support\Db::table('roles')->where('id', $id)->delete();
    }
    foreach ($cleanup['menu_ids'] as $id) {
        support\Db::table('menus')->where('id', $id)->delete();
    }
    foreach ($cleanup['dept_ids'] as $id) {
        support\Db::table('departments')->where('id', $id)->delete();
    }
    foreach ($cleanup['configs'] as $key => $value) {
        support\Db::table('system_configs')->where('config_key', $key)->update(['config_value' => $value]);
    }
    if ($cleanup['configs'] !== []) {
        (new app\repository\system\SystemConfigRepository())->forgetCache();
    }
});

/** @param array{json: mixed} $r */
function respData(array $r): mixed
{
    return is_array($r['json']) ? ($r['json']['data'] ?? null) : null;
}

/** @param array{json: mixed} $r */
function respCode(array $r): ?int
{
    return is_array($r['json']) && is_int($r['json']['code'] ?? null) ? $r['json']['code'] : null;
}

/**
 * 在树形结构（数组，节点可能嵌套 children）里递归查找 id 匹配的节点。
 *
 * @param array<int, array<string, mixed>> $nodes
 * @return array<string, mixed>|null
 */
function treeFindNode(array $nodes, int $id): ?array
{
    foreach ($nodes as $node) {
        if (is_array($node) && (int) ($node['id'] ?? -1) === $id) {
            return $node;
        }
        if (is_array($node) && is_array($node['children'] ?? null)) {
            $found = treeFindNode($node['children'], $id);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

/**
 * 取一对可用的验证码 key/明文（明文直接从服务同一个 Redis 读，不走图形识别）。
 *
 * @param list<string> $api
 * @return array{0: string, 1: string}
 */
function solveCaptcha(string $base, array $api): array
{
    $r = http('GET', "{$base}/adminapi/auth/captcha", $api);
    $key = (string) (respData($r)['key'] ?? '');

    return [$key, (string) support\Redis::get('captcha.' . $key)];
}

/** 用给定账号密码登录，返回 token（登录失败返回空字符串）。
 *
 * @param list<string> $api
 */
function loginAs(string $base, array $api, string $username, string $password): string
{
    [$key, $code] = solveCaptcha($base, $api);
    $r = http('POST', "{$base}/adminapi/auth/login", $api, [
        'username'    => $username,
        'password'    => $password,
        'captcha_key' => $key,
        'captcha'     => $code,
    ]);

    return (string) (respData($r)['token'] ?? '');
}

echo "\n=== M1a：认证 ===\n";
$r = http('GET', "{$base}/adminapi/auth/captcha", $api);
check('captcha：data 为 {key, image}', isEnvelope($r['json']) && array_keys((array) respData($r)) === ['key', 'image'], $r['body']);
check('captcha：image 为 PNG data URI', str_starts_with((string) (respData($r)['image'] ?? ''), 'data:image/png;base64,'));
$captchaKey = (string) (respData($r)['key'] ?? '');
$captcha = (string) support\Redis::get('captcha.' . $captchaKey);

$r = http('POST', "{$base}/adminapi/auth/login", $api, ['username' => $username, 'password' => $password, 'captcha_key' => $captchaKey, 'captcha' => $captcha]);
check('login：code 200，data 为 {token, admin}', respCode($r) === 200 && array_keys((array) respData($r)) === ['token', 'admin'], $r['body']);
check('login：admin 不含 password', is_array(respData($r)['admin'] ?? null) && !array_key_exists('password', respData($r)['admin']));
$token = (string) (respData($r)['token'] ?? '');
$auth = [...$api, "Authorization: Bearer {$token}"];

echo "\n=== M1a：启动接口 ===\n";
$info = http('GET', "{$base}/adminapi/auth/info", $auth);
check('auth/info：data 为 {admin, routes, permissions}', array_keys((array) respData($info)) === ['admin', 'routes', 'permissions'], $info['body']);
check('auth/info：超管 permissions 首位为 *', (respData($info)['permissions'][0] ?? null) === '*');
$routes = http('GET', "{$base}/adminapi/system/menu/routes", $auth);
check('menu/routes 与 auth/info.routes 完全一致', respData($routes) === (respData($info)['routes'] ?? false));
$global = http('GET', "{$base}/adminapi/system/config/global", $auth);
$globalData = (array) respData($global);
check('config/global：扁平对象且含 site_name', isset($globalData['site_name']), $global['body']);
check('config/global：不含凭据类键', $globalData !== [] && array_filter(array_keys($globalData), static fn ($key): bool => app\service\system\SystemConfigService::isSensitiveKey((string) $key)) === []);
$r = http('GET', "{$base}/adminapi/auth/info", $api);
check('未登录：HTTP 200 + code 401', $r['status'] === 200 && respCode($r) === 401, $r['body']);

echo "\n=== M1a：系统管理 ===\n";
$r = http('GET', "{$base}/adminapi/system/admin?limit=500", $auth);
check('admin 列表：{list, pagination}，limit 截断为 100', array_keys((array) respData($r)) === ['list', 'pagination'] && (respData($r)['pagination']['per_page'] ?? null) === 100, $r['body']);
$r = http('GET', "{$base}/adminapi/system/admin/{$contractAdminId}", $auth);
$detail = (array) respData($r);
check('admin 详情：含 roles/permissions/menu_ids，不含 password', isset($detail['roles'], $detail['permissions'], $detail['menu_ids']) && !array_key_exists('password', $detail), $r['body']);
$r = http('GET', "{$base}/adminapi/system/admin/role/options", $auth);
check('admin/role/options：每项只有 id,name,title', array_keys((array) (respData($r)[0] ?? [])) === ['id', 'name', 'title'], $r['body']);
$r = http('GET', "{$base}/adminapi/system/role", $auth);
$roleRow = (array) (respData($r)['list'][0] ?? []);
check('role 列表：行含 admins_count、menus_count、dept_ids', isset($roleRow['admins_count'], $roleRow['menus_count']) && array_key_exists('dept_ids', $roleRow), $r['body']);
$r = http('GET', "{$base}/adminapi/system/role/1", $auth);
check('role 详情：只有 {menu_ids, menus}', array_keys((array) respData($r)) === ['menu_ids', 'menus'], $r['body']);
$r = http('GET', "{$base}/adminapi/system/menu", $auth);
check('menu 列表：树形数组', isset(respData($r)[0]['children']), $r['body']);
$r = http('GET', "{$base}/adminapi/system/menu/options", $auth);
check('menu/options：根为虚拟节点 id=0「根目录」', (respData($r)[0]['id'] ?? null) === 0 && (respData($r)[0]['title'] ?? null) === '根目录', $r['body']);
$r = http('GET', "{$base}/adminapi/system/department", $auth);
check('department 列表：树形数组', isset(respData($r)[0]['children']), $r['body']);
$r = http('GET', "{$base}/adminapi/system/department/options", $auth);
check('department/options：节点为 {id,parent_id,name,code,children}', array_keys((array) (respData($r)[0] ?? [])) === ['id', 'parent_id', 'name', 'code', 'children'], $r['body']);

$roleName = "contract_role_{$suffix}";
$r = http('POST', "{$base}/adminapi/system/role", $auth, ['name' => $roleName, 'title' => '契约检查角色', 'data_scope' => 5, 'dept_ids' => [1]]);
$roleId = (int) (respData($r)['id'] ?? 0);
check('role 新建（data_scope=5 + dept_ids）', $roleId > 0, $r['body']);
$cleanup['role_ids'][] = $roleId; // 保险：下面的手动清理若因中途异常未执行，shutdown 兜底
$r = http('GET', "{$base}/adminapi/system/role?keyword={$roleName}", $auth);
check('role 列表回显 dept_ids', (respData($r)['list'][0]['dept_ids'] ?? null) === [1], $r['body']);
$r = http('DELETE', "{$base}/adminapi/system/role/{$roleId}", $auth);
check('role 删除', respCode($r) === 200, $r['body']);
support\Db::table('role_departments')->where('role_id', $roleId)->delete();
support\Db::table('roles')->where('id', $roleId)->delete();

echo "\n=== M1a：系统管理 - 菜单 ===\n";
$r = http('POST', "{$base}/adminapi/system/menu", $auth, [
    'parent_id' => 2,
    'type'      => 2,
    'title'     => "契约菜单A_{$suffix}",
    'name'      => "ContractMenuA{$suffix}",
    'path'      => "/contract-{$suffix}-a",
    'component' => 'contract/Placeholder',
]);
$menuAId = (int) (respData($r)['id'] ?? 0);
check('menu store：新建返回 id', respCode($r) === 200 && $menuAId > 0, $r['body']);
$cleanup['menu_ids'][] = $menuAId;

$r = http('POST', "{$base}/adminapi/system/menu", $auth, [
    'parent_id' => 2,
    'type'      => 2,
    'title'     => "契约菜单B_{$suffix}",
    'name'      => "ContractMenuB{$suffix}",
    'path'      => "/contract-{$suffix}-b",
    'component' => 'contract/Placeholder',
]);
$menuBId = (int) (respData($r)['id'] ?? 0);
check('menu store：第二个菜单同样成功（供 batch-sort 用）', respCode($r) === 200 && $menuBId > 0, $r['body']);
$cleanup['menu_ids'][] = $menuBId;

// MenuController::rules() 的 type 恒 required（不是 sometimes），name/path/component 在 type=2 时
// required_if：更新时即便只想改 title，也要把这几个字段原样带上（existsName/existsPath 排除自身）。
$r = http('PUT', "{$base}/adminapi/system/menu/{$menuAId}", $auth, [
    'type'      => 2,
    'title'     => "契约菜单A改_{$suffix}",
    'name'      => "ContractMenuA{$suffix}",
    'path'      => "/contract-{$suffix}-a",
    'component' => 'contract/Placeholder',
]);
$tree = http('GET', "{$base}/adminapi/system/menu", $auth);
$nodeA = treeFindNode((array) respData($tree), $menuAId);
check('menu update：新标题在菜单树里回显', respCode($r) === 200 && ($nodeA['title'] ?? null) === "契约菜单A改_{$suffix}", $r['body']);

$r = http('PUT', "{$base}/adminapi/system/menu/{$menuAId}/status", $auth, ['status' => 0]);
$tree = http('GET', "{$base}/adminapi/system/menu", $auth);
$nodeA = treeFindNode((array) respData($tree), $menuAId);
check('menu status：菜单树里 status=0', respCode($r) === 200 && (int) ($nodeA['status'] ?? -1) === 0, $r['body']);

$r = http('POST', "{$base}/adminapi/system/menu/batch-sort", $auth, [
    'items' => [
        ['id' => $menuBId, 'parent_id' => 2, 'sort' => 1],
        ['id' => $menuAId, 'parent_id' => 2, 'sort' => 2],
    ],
]);
$tree = http('GET', "{$base}/adminapi/system/menu", $auth);
$nodeA = treeFindNode((array) respData($tree), $menuAId);
$nodeB = treeFindNode((array) respData($tree), $menuBId);
check('menu batch-sort：按提交顺序重排为 10/20', respCode($r) === 200 && (int) ($nodeB['sort'] ?? -1) === 10 && (int) ($nodeA['sort'] ?? -1) === 20, $r['body']);

$r = http('DELETE', "{$base}/adminapi/system/menu/{$menuAId}", $auth);
$options = http('GET', "{$base}/adminapi/system/menu/options", $auth);
check('menu delete：菜单选项树里不再出现', respCode($r) === 200 && treeFindNode((array) respData($options), $menuAId) === null, $r['body']);

$r = http('POST', "{$base}/adminapi/system/menu", $auth, [
    'parent_id' => 2,
    'type'      => 2,
    'title'     => "契约菜单C_{$suffix}",
    'name'      => "ContractMenuC{$suffix}",
    'path'      => "/contract-{$suffix}-c",
    'component' => 'contract/Placeholder',
]);
$menuCId = (int) (respData($r)['id'] ?? 0);
$cleanup['menu_ids'][] = $menuCId;
$r = http('POST', "{$base}/adminapi/system/menu", $auth, [
    'parent_id' => 2,
    'type'      => 2,
    'title'     => "契约菜单D_{$suffix}",
    'name'      => "ContractMenuD{$suffix}",
    'path'      => "/contract-{$suffix}-d",
    'component' => 'contract/Placeholder',
]);
$menuDId = (int) (respData($r)['id'] ?? 0);
$cleanup['menu_ids'][] = $menuDId;
$r = http('POST', "{$base}/adminapi/system/menu/batch-delete", $auth, ['ids' => [$menuCId, $menuDId]]);
$tree = http('GET', "{$base}/adminapi/system/menu", $auth);
check(
    'menu batch-delete：两个菜单都从菜单树消失',
    respCode($r) === 200
        && treeFindNode((array) respData($tree), $menuCId) === null
        && treeFindNode((array) respData($tree), $menuDId) === null,
    $r['body']
);

echo "\n=== M1a：系统管理 - 角色（补全） ===\n";
$r = http('GET', "{$base}/adminapi/system/role/permission/tree", $auth);
check('role permission/tree：树形数组', respCode($r) === 200 && isset(respData($r)[0]['children']), $r['body']);
$r = http('GET', "{$base}/adminapi/system/role/menu/tree", $auth);
check('role menu/tree：树形数组', respCode($r) === 200 && isset(respData($r)[0]['children']), $r['body']);
$r = http('GET', "{$base}/adminapi/system/role/options", $auth);
check('role options：每项只有 id,name,title', respCode($r) === 200 && array_keys((array) (respData($r)[0] ?? [])) === ['id', 'name', 'title'], $r['body']);

$roleName2 = "contract_role2_{$suffix}";
$r = http('POST', "{$base}/adminapi/system/role", $auth, ['name' => $roleName2, 'title' => '契约角色2']);
$role2Id = (int) (respData($r)['id'] ?? 0);
check('role store：第二个角色（用于 update/status/授权）', respCode($r) === 200 && $role2Id > 0, $r['body']);
$cleanup['role_ids'][] = $role2Id;

$r = http('PUT', "{$base}/adminapi/system/role/{$role2Id}", $auth, ['title' => "契约角色2改_{$suffix}"]);
$list = http('GET', "{$base}/adminapi/system/role?keyword={$roleName2}", $auth);
check('role update：新标题在列表里回显', respCode($r) === 200 && (respData($list)['list'][0]['title'] ?? null) === "契约角色2改_{$suffix}", $r['body']);

$r = http('PUT', "{$base}/adminapi/system/role/{$role2Id}/status", $auth, ['status' => 0]);
$list = http('GET', "{$base}/adminapi/system/role?keyword={$roleName2}", $auth);
check('role status：列表里 status=0', respCode($r) === 200 && (int) (respData($list)['list'][0]['status'] ?? -1) === 0, $r['body']);

$r = http('PUT', "{$base}/adminapi/system/role/{$role2Id}/assign-permissions", $auth, ['menu_ids' => [20]]);
$perm = http('GET', "{$base}/adminapi/system/role/{$role2Id}/permissions", $auth);
check(
    'role assign-permissions + {id}/permissions：新授权菜单在 menu_ids 里',
    respCode($r) === 200 && respCode($perm) === 200 && in_array(20, (array) (respData($perm)['menu_ids'] ?? []), true),
    $r['body']
);

$roleName3 = "contract_role3_{$suffix}";
$r = http('POST', "{$base}/adminapi/system/role", $auth, ['name' => $roleName3, 'title' => '契约角色3']);
$role3Id = (int) (respData($r)['id'] ?? 0);
$cleanup['role_ids'][] = $role3Id;
$roleName4 = "contract_role4_{$suffix}";
$r = http('POST', "{$base}/adminapi/system/role", $auth, ['name' => $roleName4, 'title' => '契约角色4']);
$role4Id = (int) (respData($r)['id'] ?? 0);
$cleanup['role_ids'][] = $role4Id;
$r = http('POST', "{$base}/adminapi/system/role/batch-delete", $auth, ['ids' => [$role3Id, $role4Id]]);
$list3 = http('GET', "{$base}/adminapi/system/role?keyword={$roleName3}", $auth);
$list4 = http('GET', "{$base}/adminapi/system/role?keyword={$roleName4}", $auth);
check(
    'role batch-delete：两个角色都从列表消失',
    respCode($r) === 200 && (respData($list3)['list'] ?? []) === [] && (respData($list4)['list'] ?? []) === [],
    $r['body']
);

echo "\n=== M1a：系统管理 - 部门（补全） ===\n";
$deptName = "contract_dept_{$suffix}";
$r = http('POST', "{$base}/adminapi/system/department", $auth, ['parent_id' => 1, 'name' => $deptName]);
$deptId = (int) (respData($r)['id'] ?? 0);
check('department store：新建返回 id', respCode($r) === 200 && $deptId > 0, $r['body']);
$cleanup['dept_ids'][] = $deptId;

$r = http('GET', "{$base}/adminapi/system/department/{$deptId}", $auth);
check('department show：详情含新建的名称', respCode($r) === 200 && (respData($r)['name'] ?? null) === $deptName, $r['body']);

$deptName2 = "contract_dept2_{$suffix}";
$r = http('PUT', "{$base}/adminapi/system/department/{$deptId}", $auth, ['name' => $deptName2]);
$show = http('GET', "{$base}/adminapi/system/department/{$deptId}", $auth);
check('department update：详情里读到新名称', respCode($r) === 200 && (respData($show)['name'] ?? null) === $deptName2, $r['body']);

$r = http('PUT', "{$base}/adminapi/system/department/{$deptId}/status", $auth, ['status' => 0]);
$show = http('GET', "{$base}/adminapi/system/department/{$deptId}", $auth);
check('department status：详情里 status=0', respCode($r) === 200 && (int) (respData($show)['status'] ?? -1) === 0, $r['body']);

$r = http('DELETE', "{$base}/adminapi/system/department/{$deptId}", $auth);
$show = http('GET', "{$base}/adminapi/system/department/{$deptId}", $auth);
check('department delete：再查详情变成业务错误（code 400）', respCode($r) === 200 && respCode($show) === 400, $r['body']);

echo "\n=== M1a：系统管理 - 管理员（补全） ===\n";
$adminUsername1 = "contract1_{$suffix}";
$r = http('POST', "{$base}/adminapi/system/admin", $auth, [
    'username' => $adminUsername1,
    'email'    => "{$adminUsername1}@contract.local",
    'password' => 'Contract#2026',
]);
$admin1Id = (int) (respData($r)['id'] ?? 0);
check('admin store：新建返回 id', respCode($r) === 200 && $admin1Id > 0, $r['body']);
$cleanup['admin_ids'][] = $admin1Id;

$r = http('PUT', "{$base}/adminapi/system/admin/{$admin1Id}", $auth, ['nickname' => "契约昵称_{$suffix}"]);
$show = http('GET', "{$base}/adminapi/system/admin/{$admin1Id}", $auth);
check('admin update：详情里读到新昵称', respCode($r) === 200 && (respData($show)['nickname'] ?? null) === "契约昵称_{$suffix}", $r['body']);

$r = http('PUT', "{$base}/adminapi/system/admin/{$admin1Id}/status", $auth, ['status' => 0]);
$show = http('GET', "{$base}/adminapi/system/admin/{$admin1Id}", $auth);
check('admin status：详情里 status=0', respCode($r) === 200 && (int) (respData($show)['status'] ?? -1) === 0, $r['body']);

$r = http('DELETE', "{$base}/adminapi/system/admin/{$admin1Id}", $auth);
check(
    'admin delete：再查详情变成 404',
    respCode($r) === 200 && respCode(http('GET', "{$base}/adminapi/system/admin/{$admin1Id}", $auth)) === 404,
    $r['body']
);

$adminUsername2 = "contract2_{$suffix}";
$r = http('POST', "{$base}/adminapi/system/admin", $auth, [
    'username' => $adminUsername2,
    'email'    => "{$adminUsername2}@contract.local",
    'password' => 'Contract#2026',
]);
$admin2Id = (int) (respData($r)['id'] ?? 0);
$cleanup['admin_ids'][] = $admin2Id;
$newPassword2 = 'Contract#2027';
$r = http('PUT', "{$base}/adminapi/system/admin/{$admin2Id}/reset-password", $auth, ['password' => $newPassword2]);
$loginToken2 = loginAs($base, $api, $adminUsername2, $newPassword2);
check('admin reset-password：新密码可登录', respCode($r) === 200 && $loginToken2 !== '', $r['body']);

$adminUsername3 = "contract3_{$suffix}";
$adminPassword3 = 'Contract#2026';
$r = http('POST', "{$base}/adminapi/system/admin", $auth, [
    'username' => $adminUsername3,
    'email'    => "{$adminUsername3}@contract.local",
    'password' => $adminPassword3,
]);
$admin3Id = (int) (respData($r)['id'] ?? 0);
$cleanup['admin_ids'][] = $admin3Id;
$admin3Token = loginAs($base, $api, $adminUsername3, $adminPassword3);
$admin3Auth = [...$api, "Authorization: Bearer {$admin3Token}"];
$newPassword3 = 'Contract#2028';
$r = http('PUT', "{$base}/adminapi/system/admin/change-password", $admin3Auth, [
    'old_password' => $adminPassword3,
    'new_password' => $newPassword3,
]);
check(
    'admin change-password：成功后旧 token 失效',
    respCode($r) === 200 && respCode(http('GET', "{$base}/adminapi/auth/info", $admin3Auth)) === 401,
    $r['body']
);

$adminUsername4 = "contract4_{$suffix}";
$r = http('POST', "{$base}/adminapi/system/admin", $auth, [
    'username' => $adminUsername4,
    'email'    => "{$adminUsername4}@contract.local",
    'password' => 'Contract#2026',
]);
$admin4Id = (int) (respData($r)['id'] ?? 0);
$cleanup['admin_ids'][] = $admin4Id;
$adminUsername5 = "contract5_{$suffix}";
$r = http('POST', "{$base}/adminapi/system/admin", $auth, [
    'username' => $adminUsername5,
    'email'    => "{$adminUsername5}@contract.local",
    'password' => 'Contract#2026',
]);
$admin5Id = (int) (respData($r)['id'] ?? 0);
$cleanup['admin_ids'][] = $admin5Id;
$r = http('POST', "{$base}/adminapi/system/admin/batch-delete", $auth, ['ids' => [$admin4Id, $admin5Id]]);
check('admin batch-delete：返回 {count: 2}', respCode($r) === 200 && (respData($r)['count'] ?? null) === 2, $r['body']);

// ---------------------------------------------------------------- M1b
echo "\n=== M1b：系统配置 ===\n";
$r = http('GET', "{$base}/adminapi/system/config/groups", $auth);
check('config/groups：5 个固定分组', array_keys((array) respData($r)) === ['basic', 'email', 'sms', 'storage', 'payment'], $r['body']);

$r = http('GET', "{$base}/adminapi/system/config?group=basic", $auth);
$siteNameRow = [];
foreach ((array) respData($r) as $row) {
    if (is_array($row) && ($row['config_key'] ?? null) === 'site_name') {
        $siteNameRow = $row;
    }
}
check('config?group=basic：原始行数组，含 site_name，config_value 为字符串', array_is_list((array) respData($r)) && isset($siteNameRow['id']) && is_string($siteNameRow['config_value'] ?? null), $r['body']);
$siteNameId = (int) ($siteNameRow['id'] ?? 0);
$siteNameOriginal = (string) ($siteNameRow['config_value'] ?? '');
$cleanup['configs']['site_name'] = $siteNameOriginal; // 保险：中途异常时由 shutdown 写回原值

$r = http('GET', "{$base}/adminapi/system/config/{$siteNameId}", $auth);
check('config/{id}：单条原始行', (respData($r)['config_key'] ?? null) === 'site_name', $r['body']);

$r = http('PUT', "{$base}/adminapi/system/config/{$siteNameId}", $auth, ['config_value' => "契约站点_{$suffix}"]);
$global = http('GET', "{$base}/adminapi/system/config/global", $auth);
check('config update：data 为 true，config/global 立即读到新值（缓存已失效）', respCode($r) === 200 && respData($r) === true && (respData($global)['site_name'] ?? null) === "契约站点_{$suffix}", $r['body']);

$r = http('POST', "{$base}/adminapi/system/config/batch-update", $auth, ['configs' => [['config_key' => 'site_name', 'config_value' => "契约站点2_{$suffix}"]]]);
$show = http('GET', "{$base}/adminapi/system/config/{$siteNameId}", $auth);
check('config batch-update：详情读到新值', respCode($r) === 200 && (respData($show)['config_value'] ?? null) === "契约站点2_{$suffix}", $r['body']);

$r = http('POST', "{$base}/adminapi/system/config/batch-update", $auth, ['configs' => [
    ['config_key' => 'site_name', 'config_value' => "契约站点3_{$suffix}"],
    ['config_key' => "contract_missing_{$suffix}", 'config_value' => 'x'],
]]);
$show = http('GET', "{$base}/adminapi/system/config/{$siteNameId}", $auth);
check('config batch-update：含未知键时整批失败（事务），排在前面的键也没有落库', respCode($r) === 400 && (respData($show)['config_value'] ?? null) === "契约站点2_{$suffix}", $r['body']);

$r = http('POST', "{$base}/adminapi/system/config/batch-update", $auth, ['configs' => [['config_key' => 'site_name', 'config_value' => $siteNameOriginal]]]);
check('config batch-update：site_name 写回原值', respCode($r) === 200, $r['body']);

http('GET', "{$base}/adminapi/system/config/global", $auth); // 预热配置缓存
$warmed = support\Cache::has('system_config.all') || support\Cache::has('system_config.public');
$r = http('POST', "{$base}/adminapi/system/config/clear-cache", $auth);
check(
    'config clear-cache：配置缓存被清掉，当前 token 仍然有效（不再清全站缓存）',
    $warmed
        && respCode($r) === 200
        && !support\Cache::has('system_config.all')
        && !support\Cache::has('system_config.public')
        && respCode(http('GET', "{$base}/adminapi/auth/info", $auth)) === 200,
    $r['body']
);

echo "\n=== M1b：数据字典 ===\n";
$dictCode = "contract_dict_{$suffix}";
$r = http('POST', "{$base}/adminapi/system/dictionary", $auth, ['name' => "契约字典_{$suffix}", 'code' => $dictCode, 'status' => 1]);
$dictId = (int) (respData($r)['id'] ?? 0);
check('dictionary store：返回新建行（含 id）', respCode($r) === 200 && $dictId > 0, $r['body']);
$cleanup['dict_ids'][] = $dictId;

$r = http('POST', "{$base}/adminapi/system/dictionary", $auth, ['name' => '非法编码', 'code' => 'bad code!']);
check('dictionary store：code 不是 alpha_dash → 422，errors.code', respCode($r) === 422 && isset(respData($r)['errors']['code']), $r['body']);

$r = http('GET', "{$base}/adminapi/system/dictionary?keyword={$dictCode}&page=1&limit=100", $auth);
$dictRow = [];
foreach ((array) (respData($r)['list'] ?? []) as $row) {
    if (is_array($row) && (int) ($row['id'] ?? 0) === $dictId) {
        $dictRow = $row;
    }
}
check('dictionary 列表：{list, pagination}，行含 items_count', array_keys((array) respData($r)) === ['list', 'pagination'] && array_key_exists('items_count', $dictRow), $r['body']);

$r = http('POST', "{$base}/adminapi/system/dictionary/item", $auth, ['dictionary_id' => $dictId, 'label' => '甲', 'value' => 'a', 'sort' => 1]);
$itemAId = (int) (respData($r)['id'] ?? 0);
check('dictionary item store：返回新建项', respCode($r) === 200 && $itemAId > 0, $r['body']);
$r = http('POST', "{$base}/adminapi/system/dictionary/item", $auth, ['dictionary_id' => $dictId, 'label' => '乙', 'value' => 'b', 'sort' => 2]);
$itemBId = (int) (respData($r)['id'] ?? 0);
$r = http('POST', "{$base}/adminapi/system/dictionary/item", $auth, ['dictionary_id' => $dictId, 'label' => '重复', 'value' => 'a']);
$items = http('GET', "{$base}/adminapi/system/dictionary/{$dictId}/items", $auth);
check('dictionary item store：同一字典内 value 重复被拒，项数不变', in_array(respCode($r), [400, 422], true) && count((array) respData($items)) === 2, $r['body']);
check('dictionary {id}/items：纯数组', $itemBId > 0 && array_is_list((array) respData($items)), $items['body']);

$r = http('GET', "{$base}/adminapi/system/dictionary/{$dictId}", $auth);
check('dictionary 详情：含 items（两项）', respCode($r) === 200 && count((array) (respData($r)['items'] ?? [])) === 2, $r['body']);

$r = http('GET', "{$base}/adminapi/system/dictionary/options?code={$dictCode}", $auth);
$labels = array_column((array) respData($r), 'label');
check('dictionary options：返回启用的两项', count($labels) === 2 && in_array('甲', $labels, true) && in_array('乙', $labels, true), $r['body']);

$r = http('PUT', "{$base}/adminapi/system/dictionary/item/{$itemAId}", $auth, ['label' => '甲改']);
$opts = http('GET', "{$base}/adminapi/system/dictionary/options?code={$dictCode}", $auth);
check('dictionary item update：options 缓存被刷新，读到新标签', respCode($r) === 200 && in_array('甲改', array_column((array) respData($opts), 'label'), true), $r['body']);

$r = http('GET', "{$base}/adminapi/system/dictionary/batch-options?codes={$dictCode},contract_none_{$suffix}", $auth);
$batch = (array) respData($r);
check('dictionary batch-options：按 code 分组，未知 code 没有数据', count((array) ($batch[$dictCode] ?? [])) === 2 && (array) ($batch["contract_none_{$suffix}"] ?? []) === [], $r['body']);

$r = http('DELETE', "{$base}/adminapi/system/dictionary/item/{$itemBId}", $auth);
$items = http('GET', "{$base}/adminapi/system/dictionary/{$dictId}/items", $auth);
check('dictionary item delete：{id}/items 只剩一项', respCode($r) === 200 && count((array) respData($items)) === 1, $r['body']);

$r = http('PUT', "{$base}/adminapi/system/dictionary/{$dictId}", $auth, ['name' => "契约字典改_{$suffix}"]);
$show = http('GET', "{$base}/adminapi/system/dictionary/{$dictId}", $auth);
check('dictionary update：详情读到新名称', respCode($r) === 200 && (respData($show)['name'] ?? null) === "契约字典改_{$suffix}", $r['body']);

$r = http('PUT', "{$base}/adminapi/system/dictionary/{$dictId}", $auth, ['status' => 0]);
$opts = http('GET', "{$base}/adminapi/system/dictionary/options?code={$dictCode}", $auth);
check('dictionary 停用：options 返回 []（缓存同样被刷新）', respCode($r) === 200 && respData($opts) === [], $r['body']);

$r = http('DELETE', "{$base}/adminapi/system/dictionary/{$dictId}", $auth);
$show = http('GET', "{$base}/adminapi/system/dictionary/{$dictId}", $auth);
check(
    'dictionary delete：详情变成业务错误，字典项被级联删除',
    respCode($r) === 200 && respCode($show) !== 200 && support\Db::table('dictionary_items')->where('dictionary_id', $dictId)->whereNull('deleted_at')->count() === 0,
    $r['body']
);

$batchDictIds = [];
foreach (['x', 'y'] as $tag) {
    $r = http('POST', "{$base}/adminapi/system/dictionary", $auth, ['name' => "契约字典{$tag}_{$suffix}", 'code' => "contract_dict_{$tag}_{$suffix}"]);
    $id = (int) (respData($r)['id'] ?? 0);
    $cleanup['dict_ids'][] = $id;
    $batchDictIds[] = $id;
}
$visible = static fn (int $id): bool => respCode(http('GET', "{$base}/adminapi/system/dictionary/{$id}", $auth)) === 200;
$existedBefore = count(array_filter($batchDictIds, $visible)) === 2;
$r = http('POST', "{$base}/adminapi/system/dictionary/batch-delete", $auth, ['ids' => $batchDictIds]);
check('dictionary batch-delete：删除前两条都在，删除后都查不到', $existedBefore && respCode($r) === 200 && array_filter($batchDictIds, $visible) === [], $r['body']);

echo "\n=== M1b：站内通知 ===\n";
$noticeTitle = "契约通知_{$suffix}";
$r = http('POST', "{$base}/adminapi/system/notification", $auth, ['title' => $noticeTitle, 'content' => '契约检查正文', 'type' => 1, 'target_type' => 1, 'status' => 1]);
$noticeId = (int) (respData($r)['id'] ?? 0);
check('notification store：返回新建行，sender_id 为当前管理员', respCode($r) === 200 && $noticeId > 0 && (int) (respData($r)['sender_id'] ?? 0) === $contractAdminId, $r['body']);
$cleanup['notification_ids'][] = $noticeId;

$r = http('POST', "{$base}/adminapi/system/notification", $auth, ['title' => "{$noticeTitle}定向", 'content' => '正文', 'type' => 1, 'target_type' => 2]);
check('notification store：target_type=2 → 422（M1 只支持全员广播）', respCode($r) === 422 && isset(respData($r)['errors']['target_type']), $r['body']);

$r = http('GET', "{$base}/adminapi/system/notification?page=1&limit=20&keyword=" . rawurlencode($noticeTitle), $auth);
$noticeRow = (array) (respData($r)['list'][0] ?? []);
check('notification 列表：按关键词找到，行含 reads_count', (int) ($noticeRow['id'] ?? 0) === $noticeId && array_key_exists('reads_count', $noticeRow), $r['body']);

$unreadCount = static fn (): int => (int) (respData(http('GET', "{$base}/adminapi/system/notification/unread-count", $auth))['count'] ?? -1);
/** @return array<mixed>|null */
$mineRowOf = static function (int $id) use ($base, $auth): ?array {
    foreach ((array) (respData(http('GET', "{$base}/adminapi/system/notification/mine?page=1&limit=20", $auth))['list'] ?? []) as $row) {
        if (is_array($row) && (int) ($row['id'] ?? 0) === $id) {
            return $row;
        }
    }

    return null;
};
$unreadBefore = $unreadCount();
$mineRow = $mineRowOf($noticeId);
check('notification mine + unread-count：新通知在「我的通知」里且 is_read=false，未读数 ≥ 1', $mineRow !== null && ($mineRow['is_read'] ?? null) === false && $unreadBefore >= 1);

$r = http('POST', "{$base}/adminapi/system/notification/{$noticeId}/read", $auth);
$unreadAfterRead = $unreadCount();
http('POST', "{$base}/adminapi/system/notification/{$noticeId}/read", $auth);
check(
    'notification {id}/read：未读数减 1，is_read 变为 true，重复标记幂等',
    respCode($r) === 200 && $unreadAfterRead === $unreadBefore - 1 && ($mineRowOf($noticeId)['is_read'] ?? null) === true && $unreadCount() === $unreadAfterRead,
    $r['body']
);
$r = http('POST', "{$base}/adminapi/system/notification/read-all", $auth);
check('notification read-all：未读数归零', respCode($r) === 200 && $unreadCount() === 0, $r['body']);

$r = http('PUT', "{$base}/adminapi/system/notification/{$noticeId}", $auth, ['title' => "{$noticeTitle}改"]);
$show = http('GET', "{$base}/adminapi/system/notification/{$noticeId}", $auth);
check('notification update + 详情：读到新标题', respCode($r) === 200 && (respData($show)['title'] ?? null) === "{$noticeTitle}改", $r['body']);
$r = http('PUT', "{$base}/adminapi/system/notification/{$noticeId}", $auth, ['target_type' => 2]);
check('notification update：target_type=2 → 422', respCode($r) === 422, $r['body']);

$r = http('DELETE', "{$base}/adminapi/system/notification/{$noticeId}", $auth);
$show = http('GET', "{$base}/adminapi/system/notification/{$noticeId}", $auth);
check('notification delete：详情不再成功，「我的通知」里也没有了', respCode($r) === 200 && respCode($show) !== 200 && $mineRowOf($noticeId) === null, $r['body']);

echo "\n=== M1b：日志（含数据权限） ===\n";
// 临时建一个「本部门」数据范围的管理员：日志的越权删除与清空都用它做，只会动到本次运行自己的数据。
$logMenuIds = array_map('intval', support\Db::table('menus')->whereIn('permission', ['system.log.login', 'system.log.operation', 'system.log.delete', 'system.log.clear'])->pluck('id')->all());
check('日志菜单种子（111–114）存在', count($logMenuIds) === 4, implode(',', $logMenuIds));
$r = http('POST', "{$base}/adminapi/system/department", $auth, ['parent_id' => 1, 'name' => "contract_logdept_{$suffix}"]);
$logDeptId = (int) (respData($r)['id'] ?? 0);
$cleanup['dept_ids'][] = $logDeptId;
$r = http('POST', "{$base}/adminapi/system/role", $auth, ['name' => "contract_logrole_{$suffix}", 'title' => '契约日志角色', 'data_scope' => 2]);
$logRoleId = (int) (respData($r)['id'] ?? 0);
$cleanup['role_ids'][] = $logRoleId;
http('PUT', "{$base}/adminapi/system/role/{$logRoleId}/assign-permissions", $auth, ['menu_ids' => $logMenuIds]);
$logAdminName = "contract_log_{$suffix}";
$logAdminPassword = 'Contract#2026';
$r = http('POST', "{$base}/adminapi/system/admin", $auth, [
    'username'      => $logAdminName,
    'email'         => "{$logAdminName}@contract.local",
    'password'      => $logAdminPassword,
    'department_id' => $logDeptId,
    'role_ids'      => [$logRoleId],
]);
$logAdminId = (int) (respData($r)['id'] ?? 0);
$cleanup['admin_ids'][] = $logAdminId;
check('日志范围账号：部门、「本部门」角色、管理员建成', $logDeptId > 0 && $logRoleId > 0 && $logAdminId > 0, $r['body']);
$logAuth = [...$api, 'Authorization: Bearer ' . loginAs($base, $api, $logAdminName, $logAdminPassword)]; // 这次登录本身写一条登录日志

$r = http('GET', "{$base}/adminapi/system/log/login?page=1&limit=100", $logAuth);
$scopedLogins = (array) (respData($r)['list'] ?? []);
check(
    'log/login：「本部门」范围只看到本部门管理员的登录日志',
    array_keys((array) respData($r)) === ['list', 'pagination'] && array_values(array_unique(array_map('intval', array_column($scopedLogins, 'admin_id')))) === [$logAdminId],
    $r['body']
);

$r = http('GET', "{$base}/adminapi/system/log/login?keyword={$username}&page=1&limit=100", $auth);
$contractLogin = (array) (respData($r)['list'][0] ?? []);
$contractLoginId = (int) ($contractLogin['id'] ?? 0);
check(
    'log/login：keyword 按用户名搜索，行含 browser/os/login_time/login_result',
    $contractLoginId > 0 && ($contractLogin['username'] ?? null) === $username && array_diff(['browser', 'os', 'login_time', 'login_result'], array_keys($contractLogin)) === [],
    $r['body']
);

$r = http('DELETE', "{$base}/adminapi/system/log/login/{$contractLoginId}", $logAuth);
check('log/login 删除：范围外的 id → 404，记录仍在', respCode($r) === 404 && support\Db::table('admin_login_logs')->where('id', $contractLoginId)->exists(), $r['body']);
$ownLoginId = (int) ($scopedLogins[0]['id'] ?? 0);
$r = http('DELETE', "{$base}/adminapi/system/log/login/{$ownLoginId}", $auth);
check('log/login 删除：记录被删除', respCode($r) === 200 && $ownLoginId > 0 && !support\Db::table('admin_login_logs')->where('id', $ownLoginId)->exists(), $r['body']);

loginAs($base, $api, $logAdminName, $logAdminPassword); // 再登录一次，范围内重新有一条登录日志
$r = http('POST', "{$base}/adminapi/system/log/login/clear", $logAuth);
$scopedLoginTotal = (int) (respData(http('GET', "{$base}/adminapi/system/log/login?page=1&limit=1", $logAuth))['pagination']['total'] ?? -1);
check(
    'log/login/clear：只清掉范围内的登录日志，范围外的不动',
    respCode($r) === 200 && $scopedLoginTotal === 0 && support\Db::table('admin_login_logs')->where('id', $contractLoginId)->exists(),
    $r['body']
);

$r = http('GET', "{$base}/adminapi/system/log/operation?page=1&limit=100", $logAuth);
$scopedOps = (array) (respData($r)['list'] ?? []);
check(
    'log/operation：中间件记下了范围账号的写请求（含上一步的清空），范围内只看到自己的',
    array_values(array_unique(array_map('intval', array_column($scopedOps, 'admin_id')))) === [$logAdminId]
        && in_array('/adminapi/system/log/login/clear', array_column($scopedOps, 'path'), true),
    $r['body']
);
check(
    'log/operation：行含 method/path/action/description/params/result/execution_time/operation_time',
    array_diff(['method', 'path', 'action', 'description', 'params', 'result', 'execution_time', 'operation_time'], array_keys((array) ($scopedOps[0] ?? []))) === []
);

$r = http('GET', "{$base}/adminapi/system/log/operation?keyword={$username}&method=POST&page=1&limit=100", $auth);
$contractOps = (array) (respData($r)['list'] ?? []);
$maskedPassword = null;
foreach ($contractOps as $row) {
    if (is_array($row) && ($row['path'] ?? '') === '/adminapi/system/admin' && is_array($row['params'] ?? null)) {
        $maskedPassword = $row['params']['password'] ?? null;
        break;
    }
}
check('log/operation：新建管理员的操作日志里密码已脱敏为 ***', $maskedPassword === '***', $r['body']);

$contractOpId = (int) ($contractOps[0]['id'] ?? 0);
$r = http('DELETE', "{$base}/adminapi/system/log/operation/{$contractOpId}", $logAuth);
check('log/operation 删除：范围外的 id → 404，记录仍在', respCode($r) === 404 && support\Db::table('admin_operation_logs')->where('id', $contractOpId)->exists(), $r['body']);
$ownOpId = (int) ($scopedOps[0]['id'] ?? 0);
$r = http('DELETE', "{$base}/adminapi/system/log/operation/{$ownOpId}", $auth);
check('log/operation 删除：记录被删除', respCode($r) === 200 && $ownOpId > 0 && !support\Db::table('admin_operation_logs')->where('id', $ownOpId)->exists(), $r['body']);

$r = http('POST', "{$base}/adminapi/system/log/operation/clear", $logAuth);
$left = (array) (respData(http('GET', "{$base}/adminapi/system/log/operation?page=1&limit=100", $logAuth))['list'] ?? []);
check(
    'log/operation/clear：范围内清空（之后只剩这次清空请求自己的日志），范围外不动',
    respCode($r) === 200
        && array_column($left, 'path') === ['/adminapi/system/log/operation/clear']
        && support\Db::table('admin_operation_logs')->where('id', $contractOpId)->exists(),
    $r['body']
);

echo "\n=== M1b：仪表盘 ===\n";
$r = http('GET', "{$base}/adminapi/dashboard/stats", $auth);
$stats = (array) respData($r);
check('dashboard/stats：12 个键，顺序同契约', array_keys($stats) === [
    'adminCount', 'roleCount', 'menuCount', 'configCount', 'todayLoginCount', 'todayNewUsers', 'activeUsers', 'totalUsers',
    'trends', 'operationLogCount', 'loginTrend', 'registerTrend',
], $r['body']);
check(
    'dashboard/stats：trends 四个键，loginTrend 默认 7 天，registerTrend 为 []',
    array_keys((array) ($stats['trends'] ?? [])) === ['totalUsers', 'activeUsers', 'todayNewUsers', 'todayLoginCount']
        && count((array) ($stats['loginTrend'] ?? [])) === 7
        && ($stats['registerTrend'] ?? null) === []
);
$r = http('GET', "{$base}/adminapi/dashboard/stats?days=1000", $auth);
check('dashboard/stats：days 截断到 90', count((array) (respData($r)['loginTrend'] ?? [])) === 90, $r['body']);
$r = http('GET', "{$base}/adminapi/dashboard/stats", $logAuth);
check('dashboard/stats：「本部门」范围的管理员只数到本部门（adminCount=1）', (respData($r)['adminCount'] ?? null) === 1 && (int) ($stats['adminCount'] ?? 0) > 1, $r['body']);

$r = http('GET', "{$base}/adminapi/dashboard/recent-logs", $auth);
$recentLogs = (array) respData($r);
check('dashboard/recent-logs：最多 10 条登录日志（全字段）', respCode($r) === 200 && $recentLogs !== [] && count($recentLogs) <= 10 && array_key_exists('login_time', (array) ($recentLogs[0] ?? [])), $r['body']);
$r = http('GET', "{$base}/adminapi/dashboard/recent-activities", $auth);
$activities = (array) respData($r);
check(
    'dashboard/recent-activities：最多 8 条，每条 {type, username, description, time, relative_time}',
    respCode($r) === 200 && $activities !== [] && count($activities) <= 8 && array_keys((array) ($activities[0] ?? [])) === ['type', 'username', 'description', 'time', 'relative_time'],
    $r['body']
);
$r = http('GET', "{$base}/adminapi/dashboard/active-ranking?period=week", $auth);
check('dashboard/active-ranking：{period, list: [{rank, username, count}]}', (respData($r)['period'] ?? null) === 'week' && array_keys((array) (respData($r)['list'][0] ?? [])) === ['rank', 'username', 'count'], $r['body']);
$r = http('GET', "{$base}/adminapi/dashboard/active-ranking?period=year", $auth);
check('dashboard/active-ranking：非法 period → 422', respCode($r) === 422, $r['body']);

echo "\n=== M1a：刷新与登出 ===\n";
$r = http('POST', "{$base}/adminapi/auth/refresh", $auth);
$newToken = (string) (respData($r)['token'] ?? '');
check('refresh：返回新 token', $newToken !== '' && $newToken !== $token, $r['body']);
check('refresh 后旧 token 失效', respCode(http('GET', "{$base}/adminapi/auth/info", $auth)) === 401);
$auth = [...$api, "Authorization: Bearer {$newToken}"];
check('新 token 可用', respCode(http('GET', "{$base}/adminapi/auth/info", $auth)) === 200);
http('POST', "{$base}/adminapi/auth/logout", $auth);
check('logout 后 token 失效', respCode(http('GET', "{$base}/adminapi/auth/info", $auth)) === 401);

$origins = array_values(array_filter(array_map('trim', explode(',', (string) ($_ENV['CORS_ALLOWED_ORIGINS'] ?? '')))));
if ($origins !== []) {
    echo "\n=== CORS ===\n";
    $r = http('OPTIONS', "{$base}/adminapi/auth/login", ["Origin: {$origins[0]}", 'Access-Control-Request-Method: POST']);
    check('白名单来源预检：204 且回写 Origin', $r['status'] === 204 && ($r['headers']['access-control-allow-origin'] ?? '') === $origins[0], (string) $r['status']);
    $r = http('OPTIONS', "{$base}/adminapi/auth/login", ['Origin: http://not-allowed.invalid']);
    check('非白名单来源预检：403', $r['status'] === 403, (string) $r['status']);
}

echo "\n通过 {$passes} 项，失败 " . count($failures) . " 项\n";
exit($failures === [] ? 0 : 1);
