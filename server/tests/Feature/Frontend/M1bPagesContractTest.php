<?php

declare(strict_types=1);

namespace tests\Feature\Frontend;

use support\Db;
use tests\Support\ApiTestCase;

/**
 * M1b 页面的前端契约：请求参数照抄 admin/src 的实际发送方式（useListPage 把 searchForm 原样拼进 query，
 * useFormDialog 编辑时把列表行整行回传），并断言页面读取的每个字段都在。工作台见 DashboardApiTest。
 */
final class M1bPagesContractTest extends ApiTestCase
{
    private function marker(string $prefix): string
    {
        return $prefix . bin2hex(random_bytes(4));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<int>
     */
    private function ids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['id'], $rows));
    }

    public function test_dictionary_page(): void
    {
        $super = $this->actingAsAdmin('super');
        $code = $this->marker('fe_dict_');

        // DictForm 新建：defaultForm 全量（id 为 undefined，JSON 里不出现）
        $created = $this->post('/adminapi/system/dictionary', ['name' => '前端字典', 'code' => $code, 'description' => '', 'sort' => 0, 'status' => 1], $super->token)->assertOk()->data();
        $dictId = (int) $created['id'];
        $this->track('dictionaries', $dictId);

        // index.vue：keyword 搜名称/编码，status 筛状态
        $query = ['keyword' => $code, 'status' => 1, 'page' => 1, 'limit' => 20];
        $page = $this->get('/adminapi/system/dictionary', $query, $super->token)->assertOk()->data();
        $this->assertSame([$dictId], $this->ids($page['list']), '字典页按编码搜索');
        $row = $page['list'][0];
        foreach (['id', 'name', 'code', 'description', 'sort', 'status', 'created_at'] as $field) {
            $this->assertArrayHasKey($field, $row, "字典列表缺 {$field}");
        }
        $this->assertSame([$dictId], $this->ids($this->get('/adminapi/system/dictionary', ['keyword' => '前端字典'] + $query, $super->token)->assertOk()->data()['list']), '字典页按名称搜索');
        $this->assertSame([], $this->get('/adminapi/system/dictionary', ['status' => 0] + $query, $super->token)->assertOk()->data()['list'], '按状态筛选');
        // 重置搜索后 keyword 为 ''、status 为 undefined（不序列化）：不过滤
        $reset = $this->get('/adminapi/system/dictionary', ['keyword' => '', 'page' => 1, 'limit' => 20], $super->token)->assertOk()->data();
        $this->assertGreaterThanOrEqual(1, $reset['pagination']['total']);

        // DictForm 编辑：整行回传（多出 id、items_count、created_at 等；库里为 NULL 的描述回传 null）
        $this->put("/adminapi/system/dictionary/{$dictId}", array_merge($row, ['name' => '前端字典改', 'description' => null]), $super->token)->assertOk();
        $this->assertSame('前端字典改', Db::table('dictionaries')->where('id', $dictId)->value('name'));

        // DictItemForm 新建
        $item = $this->post('/adminapi/system/dictionary/item', ['dictionary_id' => $dictId, 'label' => '甲', 'value' => 'a', 'tag_type' => '', 'description' => '', 'sort' => 0, 'status' => 1], $super->token)->assertOk()->data();
        $itemId = (int) $item['id'];
        $this->track('dictionary_items', $itemId);

        // 字典项弹窗：{id}/items 返回纯数组
        $items = $this->get("/adminapi/system/dictionary/{$dictId}/items", [], $super->token)->assertOk()->data();
        $this->assertTrue(array_is_list($items));
        foreach (['id', 'dictionary_id', 'label', 'value', 'tag_type', 'description', 'sort', 'status'] as $field) {
            $this->assertArrayHasKey($field, $items[0], "字典项缺 {$field}");
        }

        // DictItemForm 编辑：整行回传；tag_type、description 为 NULL 的行回传 null
        $this->put("/adminapi/system/dictionary/item/{$itemId}", array_merge($items[0], ['label' => '甲改', 'tag_type' => null, 'description' => null]), $super->token)->assertOk();
        $this->assertSame('甲改', Db::table('dictionary_items')->where('id', $itemId)->value('label'));

        $this->delete("/adminapi/system/dictionary/item/{$itemId}", [], $super->token)->assertOk();
        $this->assertSame([], $this->get("/adminapi/system/dictionary/{$dictId}/items", [], $super->token)->assertOk()->data());
    }

    public function test_log_pages(): void
    {
        $super = $this->actingAsAdmin('super');
        $target = $this->actingAsAdmin();
        $now = date('Y-m-d H:i:s');
        Db::table('admin_login_logs')->insert([
            'admin_id' => $target->id, 'username' => $target->username, 'ip' => '127.0.0.1', 'user_agent' => 'phpunit',
            'login_time' => $now, 'login_result' => 1, 'login_message' => '登录成功', 'browser' => 'Chrome 120.0', 'os' => 'Mac OS X', 'created_at' => $now,
        ]);

        // login.vue 首次加载：keyword=''、ip=''，login_result 为 undefined（不序列化）
        $page = $this->get('/adminapi/system/log/login', ['keyword' => '', 'ip' => '', 'page' => 1, 'limit' => 20], $super->token)->assertOk()->data();
        $this->assertSame(['list', 'pagination'], array_keys($page));
        $this->assertGreaterThanOrEqual(1, $page['pagination']['total']);

        $query = ['keyword' => $target->username, 'ip' => '127.0.0.1', 'login_result' => 1, 'page' => 1, 'limit' => 20];
        $rows = $this->get('/adminapi/system/log/login', $query, $super->token)->assertOk()->data()['list'];
        $this->assertCount(1, $rows);
        foreach (['id', 'username', 'ip', 'browser', 'os', 'login_result', 'login_message', 'login_time'] as $field) {
            $this->assertArrayHasKey($field, $rows[0], "登录日志缺 {$field}");
        }
        $this->assertSame(1, $rows[0]['login_result']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $rows[0]['login_time']);
        $this->assertSame([], $this->get('/adminapi/system/log/login', ['login_result' => 0] + $query, $super->token)->assertOk()->data()['list']);

        // operation.vue：keyword 占位符是「用户名/操作/描述」，method 清空后发 ''
        $action = $this->marker('fe_action_');
        $description = $this->marker('fe_desc_');
        $opId = (int) Db::table('admin_operation_logs')->insertGetId([
            'admin_id' => $target->id, 'username' => $target->username, 'method' => 'POST', 'path' => '/adminapi/system/dictionary',
            'ip' => '127.0.0.1', 'user_agent' => 'phpunit', 'action' => $action, 'description' => $description,
            'params' => '{}', 'result' => '{"code":200,"message":"ok"}', 'operation_time' => $now, 'execution_time' => 0.012,
        ]);
        $this->track('admin_operation_logs', $opId);

        foreach (['用户名' => $target->username, '操作' => $action, '描述' => $description] as $what => $keyword) {
            $found = $this->get('/adminapi/system/log/operation', ['keyword' => $keyword, 'method' => '', 'page' => 1, 'limit' => 20], $super->token)->assertOk()->data()['list'];
            $this->assertContains($opId, $this->ids($found), "keyword 按{$what}搜索");
        }
        $posts = $this->get('/adminapi/system/log/operation', ['keyword' => $description, 'method' => 'POST', 'page' => 1, 'limit' => 20], $super->token)->assertOk()->data()['list'];
        $this->assertSame([$opId], $this->ids($posts));
        $gets = $this->get('/adminapi/system/log/operation', ['keyword' => $description, 'method' => 'GET', 'page' => 1, 'limit' => 20], $super->token)->assertOk()->data()['list'];
        $this->assertSame([], $gets);

        foreach (['id', 'username', 'method', 'path', 'action', 'description', 'ip', 'execution_time', 'operation_time'] as $field) {
            $this->assertArrayHasKey($field, $posts[0], "操作日志缺 {$field}");
        }
        $this->assertEqualsWithDelta(12.0, (float) $posts[0]['execution_time'] * 1000, 0.001, '页面按「秒 × 1000」显示毫秒');
    }

    public function test_notification_pages(): void
    {
        $super = $this->actingAsAdmin('super');
        $title = $this->marker('前端通知');

        // NotificationForm 新建
        $created = $this->post('/adminapi/system/notification', ['title' => $title, 'content' => '正文', 'type' => 1, 'target_type' => 1, 'status' => 1], $super->token)->assertOk()->data();
        $id = (int) $created['id'];
        $this->track('notifications', $id);
        // 表单允许选「指定用户」：M1 返回 422（spec §1.1 #7）
        $this->post('/adminapi/system/notification', ['title' => $title . '定向', 'content' => '正文', 'type' => 1, 'target_type' => 2, 'status' => 1], $super->token)->assertCode(422);

        // index.vue：page/limit，搜索时加 keyword、type
        $page = $this->get('/adminapi/system/notification', ['page' => 1, 'limit' => 20, 'keyword' => $title, 'type' => 1], $super->token)->assertOk()->data();
        $this->assertSame([$id], $this->ids($page['list']));
        $row = $page['list'][0];
        foreach (['id', 'title', 'content', 'type', 'target_type', 'reads_count', 'status', 'created_at'] as $field) {
            $this->assertArrayHasKey($field, $row, "通知列表缺 {$field}（content 供编辑弹窗回填）");
        }
        $this->assertSame(0, (int) $row['reads_count']);

        // 编辑：整行回传（多出 reads_count、sender_id、created_at 等）
        $this->put("/adminapi/system/notification/{$id}", array_merge($row, ['title' => $title . '改']), $super->token)->assertOk();
        $this->assertSame($title . '改', Db::table('notifications')->where('id', $id)->value('title'));

        // notification-bell：unread-count → {count}；mine(page=1, limit=10) → list[].{id,title,created_at,is_read}
        $reader = $this->actingAsAdmin();
        $count = fn (): int => (int) $this->get('/adminapi/system/notification/unread-count', [], $reader->token)->assertOk()->data()['count'];
        $before = $count();
        $this->assertGreaterThanOrEqual(1, $before);

        $mine = $this->get('/adminapi/system/notification/mine', ['page' => 1, 'limit' => 10], $reader->token)->assertOk()->data();
        $this->assertSame(['list', 'pagination'], array_keys($mine));
        $mineRow = array_values(array_filter($mine['list'], static fn (array $n): bool => (int) $n['id'] === $id))[0] ?? null;
        $this->assertNotNull($mineRow, '刚发布的广播通知出现在「我的通知」第一页');
        foreach (['id', 'title', 'created_at', 'is_read'] as $field) {
            $this->assertArrayHasKey($field, $mineRow);
        }
        $this->assertFalse($mineRow['is_read']);

        $this->post("/adminapi/system/notification/{$id}/read", [], $reader->token)->assertOk();
        $this->assertSame($before - 1, $count());
        $this->post("/adminapi/system/notification/{$id}/read", [], $reader->token)->assertOk();
        $this->assertSame($before - 1, $count(), '重复标记已读是幂等的');
        $this->post('/adminapi/system/notification/read-all', [], $reader->token)->assertOk();
        foreach (Db::table('notification_reads')->where('admin_id', $reader->id)->pluck('id') as $readId) {
            $this->track('notification_reads', (int) $readId);
        }
        $this->assertSame(0, $count());
    }

    public function test_config_page_and_header_clear_cache(): void
    {
        $super = $this->actingAsAdmin('super');

        $groups = $this->get('/adminapi/system/config/groups', [], $super->token)->assertOk()->data();
        $this->assertSame(['basic', 'email', 'sms', 'storage', 'payment'], array_keys($groups));
        $this->assertContainsOnlyString($groups);

        $configs = $this->get('/adminapi/system/config', ['group' => 'basic'], $super->token)->assertOk()->data();
        $this->assertTrue(array_is_list($configs));
        foreach (['id', 'config_key', 'config_name', 'config_value', 'config_type', 'config_desc', 'config_options', 'config_depends'] as $field) {
            $this->assertArrayHasKey($field, $configs[0], "配置行缺 {$field}");
        }

        // handleSave：当前分组的全部配置按 String(value) 回传（boolean 先 Number 再 String，得 '1' / '0'）
        $siteName = (string) Db::table('system_configs')->where('config_key', 'site_name')->value('config_value');
        $this->setConfig('site_name', $siteName); // 只为登记 tearDown 时写回原值
        $marker = $this->marker('前端站点');
        $payload = array_map(static fn (array $config): array => [
            'config_key'   => $config['config_key'],
            'config_value' => $config['config_key'] === 'site_name' ? $marker : (string) $config['config_value'],
        ], $configs);
        $this->post('/adminapi/system/config/batch-update', ['configs' => $payload], $super->token)->assertOk();

        $after = $this->get('/adminapi/system/config', ['group' => 'basic'], $super->token)->assertOk()->data();
        $this->assertSame(
            array_column($payload, 'config_value', 'config_key'),
            array_column($after, 'config_value', 'config_key'),
            '整组原样回存后读出的值不变（布尔、数字没有被改写）'
        );
        $this->assertSame($marker, $this->get('/adminapi/system/config/global', [], $super->token)->assertOk()->data()['site_name']);

        // 顶栏「清除缓存」：任何登录管理员都能点，清完 token 仍然有效
        $nobody = $this->actingAsAdmin();
        $this->post('/adminapi/system/config/clear-cache', [], $nobody->token)->assertOk();
        $this->get('/adminapi/auth/info', [], $nobody->token)->assertOk();
    }
}
