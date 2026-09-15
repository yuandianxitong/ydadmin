<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\middleware\AdminLogMiddleware;
use core\validation\ValidatorFactory;
use support\Db;
use tests\Support\ApiTestCase;
use Webman\Route;
use Webman\Route\Route as RouteObject;

final class AdminLogMiddlewareTest extends ApiTestCase
{
    /** @return list<object> 该管理员的操作日志，新的在前 */
    private function logsOf(int $adminId): array
    {
        return Db::table('admin_operation_logs')->where('admin_id', $adminId)->orderByDesc('id')->get()->all();
    }

    public function test_write_is_logged_with_masked_password_and_mapped_text(): void
    {
        $super = $this->actingAsAdmin('super');
        $username = 'alm_' . bin2hex(random_bytes(3));
        $response = $this->post('/adminapi/system/admin', [
            'username' => $username,
            'email'    => "{$username}@test.local",
            'password' => 'Secret#123',
            'nickname' => '日志探针',
        ], $super->token)->assertOk();
        $this->trackAdmin((int) $response->data()['id']);

        $logs = $this->logsOf($super->id);
        $this->assertCount(1, $logs);
        $log = $logs[0];
        $this->assertSame('POST', $log->method);
        $this->assertSame('/adminapi/system/admin', $log->path);
        $this->assertSame($super->username, $log->username);
        $this->assertSame(lang('admin_log.admin_create'), $log->action);
        $this->assertSame(lang('admin_log.admin_create_desc'), $log->description);
        $params = json_decode((string) $log->params, true);
        $this->assertSame('***', $params['password']);
        $this->assertSame($username, $params['username']);
        $this->assertStringNotContainsString('Secret#123', (string) $log->params);
        $this->assertSame(['code' => 200, 'message' => $response->message()], json_decode((string) $log->result, true));
        $this->assertNotNull($log->operation_time);
        $this->assertNotNull($log->execution_time);
    }

    public function test_change_password_params_are_masked(): void
    {
        $admin = $this->actingAsAdmin();
        $this->put('/adminapi/system/admin/change-password', ['old_password' => $admin->password, 'new_password' => 'N3wPassw0rd!'], $admin->token)->assertOk();

        $log = $this->logsOf($admin->id)[0];
        $this->assertSame(lang('admin_log.admin_change_password'), $log->action);
        $params = json_decode((string) $log->params, true);
        // MySQL 的 json 列不保留对象键的写入顺序，取回时按键名重排；按键排序后再比较
        ksort($params);
        $this->assertSame(['new_password' => '***', 'old_password' => '***'], $params);
        $this->assertStringNotContainsString('N3wPassw0rd!', (string) $log->params);
        $this->assertStringNotContainsString($admin->password, (string) $log->params);
    }

    public function test_operation_time_is_recorded_no_later_than_the_row_creation(): void
    {
        $admin = $this->actingAsAdmin();
        $this->put('/adminapi/system/admin/change-password', ['old_password' => $admin->password, 'new_password' => 'N3wPassw0rd!'], $admin->token)->assertOk();

        $log = $this->logsOf($admin->id)[0];
        $this->assertNotNull($log->operation_time);
        $this->assertLessThanOrEqual((string) $log->created_at, (string) $log->operation_time, 'operation_time 取请求时刻，不可能晚于落库时刻');
    }

    public function test_reads_are_not_logged(): void
    {
        $super = $this->actingAsAdmin('super');
        $this->get('/adminapi/system/admin', [], $super->token)->assertOk();
        $this->get('/adminapi/auth/info', [], $super->token)->assertOk();

        $this->assertSame([], $this->logsOf($super->id));
    }

    public function test_failed_writes_log_the_failing_code_and_paths_with_ids_still_match(): void
    {
        $super = $this->actingAsAdmin('super');
        $this->put('/adminapi/system/admin/999999999', ['nickname' => '不存在'], $super->token)->assertCode(404);
        $this->post('/adminapi/system/admin', ['username' => ''], $super->token)->assertCode(422);

        $logs = $this->logsOf($super->id);
        $this->assertCount(2, $logs);
        $this->assertSame(422, json_decode((string) $logs[0]->result, true)['code']);
        $this->assertSame(404, json_decode((string) $logs[1]->result, true)['code']);
        $this->assertSame('/adminapi/system/admin/999999999', $logs[1]->path);
        $this->assertSame(lang('admin_log.admin_update'), $logs[1]->action, '带 /{id} 的路径同样命中映射（TP8 按路径匹配时落到兜底文案）');
    }

    public function test_invalid_utf8_in_query_and_user_agent_still_stores_the_log(): void
    {
        $super = $this->actingAsAdmin('super');
        $this->call('PUT', '/adminapi/system/admin/999999999?probe=%FF', ['nickname' => '不存在'], $super->token, ['User-Agent' => "ua\xFF"])->assertCode(404);

        $logs = $this->logsOf($super->id);
        $this->assertCount(1, $logs, '带非法 UTF-8 的写请求也必须留下操作日志');
        $this->assertTrue(mb_check_encoding((string) $logs[0]->user_agent, 'UTF-8'));
        $this->assertStringStartsWith('ua', (string) $logs[0]->user_agent);
        $params = json_decode((string) $logs[0]->params, true);
        $this->assertIsArray($params);
        $this->assertArrayHasKey('probe', $params);
        $this->assertTrue(mb_check_encoding((string) $params['probe'], 'UTF-8'));
        $this->assertSame('不存在', $params['nickname']);
    }

    public function test_config_values_that_may_be_credentials_are_masked(): void
    {
        $super = $this->actingAsAdmin('super');
        $id = (int) Db::table('system_configs')->where('config_key', 'site_name')->value('id');
        // 登记原值：tearDown 时恢复 site_name 并清配置缓存
        $this->setConfig('site_name', (string) Db::table('system_configs')->where('id', $id)->value('config_value'));

        $this->put("/adminapi/system/config/{$id}", ['config_value' => '单项探针'], $super->token);
        $this->post('/adminapi/system/config/batch-update', ['configs' => [
            ['config_key' => 'site_name', 'config_value' => '批量探针'],
            ['config_key' => 'storage_oss_access_secret', 'config_value' => 'leak-me'],
        ]], $super->token);

        [$batch, $single] = $this->logsOf($super->id);
        $this->assertSame(['config_value' => '***'], json_decode((string) $single->params, true), '按 id 更新看不出键名，整字段脱敏');
        $configs = json_decode((string) $batch->params, true)['configs'];
        $this->assertSame('批量探针', $configs[0]['config_value']);
        $this->assertSame('***', $configs[1]['config_value']);
        $this->assertStringNotContainsString('leak-me', (string) $batch->params);
    }

    public function test_every_logged_write_route_has_action_text(): void
    {
        $actions = (array) config('admin_log.actions', []);
        $skip = array_values(array_map('strval', (array) config('admin_log.skip', [])));
        $routed = [];
        $skipped = [];
        $missing = [];
        /** @var RouteObject $route */
        foreach (Route::getRoutes() as $route) {
            if (!in_array(AdminLogMiddleware::class, $route->getMiddleware(), true)
                || array_intersect($route->getMethods(), ['POST', 'PUT', 'DELETE']) === []) {
                continue;
            }
            $callback = $route->getCallback();
            if (!is_array($callback) || count($callback) !== 2) {
                $missing[] = $route->getPath() . '（不是 [控制器, 方法] 形式的回调）';
                continue;
            }
            $key = AdminLogMiddleware::shortKey((string) $callback[0], (string) $callback[1]);
            if (in_array($key, $skip, true)) {
                $skipped[] = $key;

                continue;
            }
            $routed[] = $key;
            if (!isset($actions[$key])) {
                $missing[] = implode('|', $route->getMethods()) . ' ' . $route->getPath() . " → {$key}";
            }
        }

        $this->assertGreaterThanOrEqual(50, count($routed), 'M1a 25 条（auth 2、admin 7、role 6、menu 6、department 4）+ M1b 19 条（config 3、dictionary 7、log 4、notification 5）+ M1c 6 条（file 4、upload 2）');
        $this->assertSame([], $missing, "以下写路由没有在 config/admin_log.php 登记动作文案：\n" . implode("\n", $missing));

        $stale = array_values(array_filter(
            array_keys($actions),
            static fn (string $key): bool => !in_array($key, $routed, true)
        ));
        $this->assertSame([], $stale, "映射表里这些键对不上任何写路由（拼错或路由已删）：\n" . implode("\n", $stale));

        $this->assertSame([], array_values(array_diff($skip, $skipped)), 'admin_log.skip 里这些键对不上任何写路由（拼错或路由已删）');
        $this->assertSame([], array_values(array_intersect($skip, array_keys($actions))), '同一动作不能既登记文案又列入 skip');
    }

    public function test_every_mapped_lang_key_exists_in_both_locales(): void
    {
        $translator = ValidatorFactory::translator();
        $keys = ['messages.operation', 'messages.execute_operation'];
        foreach ((array) config('admin_log.actions', []) as $action => $pair) {
            $this->assertIsArray($pair, $action);
            $this->assertCount(2, $pair, $action);
            array_push($keys, ...array_values($pair));
        }
        $missing = [];
        foreach (array_unique($keys) as $key) {
            foreach (['zh_CN', 'en'] as $locale) {
                if (!$translator->hasForLocale((string) $key, $locale)) {
                    $missing[] = "{$locale}: {$key}";
                }
            }
        }

        $this->assertSame([], $missing, "缺少以下 lang 键：\n" . implode("\n", $missing));
    }
}
