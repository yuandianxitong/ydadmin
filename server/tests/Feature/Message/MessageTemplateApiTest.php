<?php

declare(strict_types=1);

namespace tests\Feature\Message;

use support\Db;
use tests\Support\ApiTestCase;

/**
 * 管理端消息模板（M6b spec §4.1）。
 *
 * 字段白名单：新增、编辑只写表单字段。wechat_*_data、site_*、variables 这些列管理端界面编辑不了，
 * 但编辑弹窗会把整行（含这些列）原样回传，所以接口必须把它们丢掉，不能覆盖。
 */
final class MessageTemplateApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/message/template';

    /** @var list<string> 经接口新增的模板 code（发请求之前就登记，断言失败也能清理） */
    private array $createdCodes = [];

    protected function tearDown(): void
    {
        try {
            if ($this->createdCodes !== []) {
                Db::table('message_templates')->whereIn('code', $this->createdCodes)->delete();
            }
        } finally {
            $this->createdCodes = [];
            parent::tearDown();
        }
    }

    private function randomCode(): string
    {
        return 't' . bin2hex(random_bytes(6));
    }

    /** 登记一个即将经接口新增的 code。 */
    private function rememberCode(?string $code = null): string
    {
        $code ??= $this->randomCode();
        $this->createdCodes[] = $code;

        return $code;
    }

    /** @param array<string, mixed> $attributes */
    private function insertTemplate(array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('message_templates')->insertGetId(array_merge([
            'name'       => '测试模板' . bin2hex(random_bytes(3)),
            'code'       => $this->randomCode(),
            'remark'     => null,
            'status'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
        $this->track('message_templates', $id);

        return $id;
    }

    /** MySQL JSON 列取回后键序会变：递归按键排序后再比较。 */
    private static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $value = array_map(static fn (mixed $item): mixed => self::canonical($item), $value);
        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private static function decodeColumn(mixed $raw): mixed
    {
        return $raw === null ? null : self::canonical(json_decode((string) $raw, true));
    }

    public function test_every_endpoint_requires_its_permission(): void
    {
        $id = $this->insertTemplate();
        $nobody = $this->actingAsAdmin();

        $this->get(self::BASE, [], $nobody->token)->assertCode(403);
        $this->get(self::BASE . "/{$id}", [], $nobody->token)->assertCode(403);
        $this->post(self::BASE, ['name' => 'x', 'code' => $this->randomCode()], $nobody->token)->assertCode(403);
        $this->put(self::BASE . "/{$id}", ['name' => 'x'], $nobody->token)->assertCode(403);
        $this->delete(self::BASE . "/{$id}", [], $nobody->token)->assertCode(403);

        // 只有 list 权限：写接口仍然 403
        $reader = $this->actingAsAdmin(['system.message.template.list']);
        $this->get(self::BASE . "/{$id}", [], $reader->token)->assertOk();
        $this->put(self::BASE . "/{$id}", ['name' => 'x'], $reader->token)->assertCode(403);
        $this->delete(self::BASE . "/{$id}", [], $reader->token)->assertCode(403);
        $this->assertNull(Db::table('message_templates')->where('id', $id)->value('deleted_at'));
    }

    public function test_list_filters_by_keyword_and_status_with_standard_pagination(): void
    {
        $tag = bin2hex(random_bytes(4));
        $enabled = $this->insertTemplate(['name' => "列表A_{$tag}", 'status' => 1]);
        $disabled = $this->insertTemplate(['name' => "列表B_{$tag}", 'status' => 0]);
        $byCode = $this->insertTemplate(['code' => "k{$tag}code", 'status' => 1]);
        $this->insertTemplate(['name' => "列表D_{$tag}", 'deleted_at' => date('Y-m-d H:i:s')]);
        $admin = $this->actingAsAdmin(['system.message.template.list']);

        $data = $this->get(self::BASE, ['keyword' => $tag, 'page' => 1, 'limit' => 100], $admin->token)->assertOk()->data();
        $this->assertSame(['list', 'pagination'], array_keys($data));
        $this->assertSame(['current_page', 'per_page', 'total', 'last_page'], array_keys($data['pagination']));
        $this->assertSame([$byCode, $disabled, $enabled], array_column($data['list'], 'id'), 'name 与 code 都参与 keyword；id 倒序；软删行不出现');

        $zero = $this->get(self::BASE, ['keyword' => $tag, 'status' => 0], $admin->token)->assertOk()->data();
        $this->assertSame([$disabled], array_column($zero['list'], 'id'), 'status=0 是有效筛选，不能当成「不筛」');

        $page = $this->get(self::BASE, ['keyword' => $tag, 'status' => 1, 'page' => 2, 'limit' => 1], $admin->token)->assertOk()->data();
        $this->assertSame([$enabled], array_column($page['list'], 'id'));
        $this->assertSame(['current_page' => 2, 'per_page' => 1, 'total' => 2, 'last_page' => 2], $page['pagination']);

        // 前端清空搜索框时带 keyword=''：等同不筛选，不报 422
        $this->get(self::BASE, ['keyword' => '', 'page' => 1, 'limit' => 1], $admin->token)->assertOk();
        $this->get(self::BASE, ['status' => 9], $admin->token)->assertCode(422);
    }

    public function test_show_returns_full_row_and_404_for_missing_or_soft_deleted(): void
    {
        $code = $this->randomCode();
        $id = $this->insertTemplate([
            'code'                 => $code,
            'wechat_official_data' => json_encode(['thing1' => '${nickname}']),
            'site_enabled'         => 1,
            'site_title'           => '注册成功',
        ]);
        $gone = $this->insertTemplate(['deleted_at' => date('Y-m-d H:i:s')]);
        $admin = $this->actingAsAdmin(['system.message.template.list']);

        $data = $this->get(self::BASE . "/{$id}", [], $admin->token)->assertOk()->data();
        $this->assertSame($id, $data['id']);
        $this->assertSame($code, $data['code']);
        $this->assertSame(['thing1' => '${nickname}'], $data['wechat_official_data']);
        $this->assertSame('注册成功', $data['site_title']);
        foreach (['name', 'remark', 'status', 'sms_enabled', 'sms_template_id', 'sms_content', 'wechat_official_enabled', 'wechat_official_template_id', 'wechat_official_url', 'wechat_mini_enabled', 'wechat_mini_template_id', 'wechat_mini_page', 'created_at'] as $field) {
            $this->assertArrayHasKey($field, $data, "编辑弹窗回填需要 {$field}");
        }

        $this->get(self::BASE . "/{$gone}", [], $admin->token)->assertCode(404);
        $this->get(self::BASE . '/999999999', [], $admin->token)->assertCode(404);
    }

    public function test_store_writes_only_form_fields_with_defaults(): void
    {
        $admin = $this->actingAsAdmin(['system.message.template.create']);
        $code = $this->rememberCode();

        $response = $this->post(self::BASE, [
            'name'                        => '自定义通知',
            'code'                        => $code,
            'remark'                      => '备注说明',
            'status'                      => 0,
            'sms_enabled'                 => 1,
            'sms_template_id'             => 'SMS_12345',
            'sms_content'                 => '您好 ${nickname}',
            'wechat_official_enabled'     => 1,
            'wechat_official_template_id' => 'tpl-official',
            'wechat_official_url'         => 'https://example.com/order/${order_no}',
            'wechat_mini_enabled'         => 0,
            'wechat_mini_template_id'     => '',
            'wechat_mini_page'            => 'pages/index/index',
            // 以下都不在表单白名单里，必须被丢弃
            'id'                          => 987654321,
            'site_enabled'                => 1,
            'site_title'                  => '越权标题',
            'site_content'                => '越权正文',
            'wechat_official_data'        => ['thing1' => '${nickname}'],
            'wechat_mini_data'            => ['thing1' => '${nickname}'],
            'variables'                   => [['key' => 'nickname', 'name' => '昵称', 'example' => '张三']],
            'created_at'                  => '2000-01-01 00:00:00',
        ], $admin->token);
        $response->assertOk();
        $this->assertSame(lang('messages.create_success'), $response->message());

        $row = Db::table('message_templates')->where('code', $code)->first();
        $this->assertNotNull($row);
        $this->assertNotSame(987654321, (int) $row->id);
        $this->assertSame(['自定义通知', '备注说明', 0], [$row->name, $row->remark, (int) $row->status]);
        $this->assertSame([1, 'SMS_12345', '您好 ${nickname}'], [(int) $row->sms_enabled, $row->sms_template_id, $row->sms_content]);
        $this->assertSame([1, 'tpl-official', 'https://example.com/order/${order_no}'], [(int) $row->wechat_official_enabled, $row->wechat_official_template_id, $row->wechat_official_url]);
        $this->assertSame([0, '', 'pages/index/index'], [(int) $row->wechat_mini_enabled, $row->wechat_mini_template_id, $row->wechat_mini_page]);
        $this->assertSame([0, '', ''], [(int) $row->site_enabled, $row->site_title, $row->site_content]);
        $this->assertNull($row->wechat_official_data);
        $this->assertNull($row->wechat_mini_data);
        $this->assertNull($row->variables);
        $this->assertNotSame('2000-01-01 00:00:00', (string) $row->created_at);
        $this->assertNull($row->deleted_at);

        // 只给必填项：其余取默认值（启用、通道全关、文本空串）
        $minimal = $this->rememberCode();
        $this->post(self::BASE, ['name' => '最小模板', 'code' => $minimal], $admin->token)->assertOk();
        $row = Db::table('message_templates')->where('code', $minimal)->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->status);
        $this->assertNull($row->remark);
        foreach (['sms_enabled', 'wechat_official_enabled', 'wechat_mini_enabled', 'site_enabled'] as $flag) {
            $this->assertSame(0, (int) $row->{$flag}, $flag);
        }
        foreach (['sms_template_id', 'sms_content', 'wechat_official_template_id', 'wechat_official_url', 'wechat_mini_template_id', 'wechat_mini_page'] as $text) {
            $this->assertSame('', $row->{$text}, $text);
        }
    }

    public function test_store_rejects_invalid_code_format_and_length(): void
    {
        $admin = $this->actingAsAdmin(['system.message.template.create']);

        foreach (['Bad_Code', '1starts_with_digit', '_leading', 'has-dash', 'has space', '中文编码'] as $bad) {
            $this->rememberCode($bad);
            $response = $this->post(self::BASE, ['name' => '格式', 'code' => $bad], $admin->token);
            $response->assertCode(422);
            $this->assertSame(lang('message.template_code_format'), $response->data()['errors']['code'] ?? null, $bad);
        }

        $tooLong = 'a' . str_repeat('b', 50);
        $this->rememberCode($tooLong);
        $response = $this->post(self::BASE, ['name' => '超长', 'code' => $tooLong], $admin->token);
        $response->assertCode(422);
        $this->assertArrayHasKey('code', $response->data()['errors']);

        $missing = $this->post(self::BASE, ['name' => '缺编码'], $admin->token);
        $missing->assertCode(422);
        $this->assertArrayHasKey('code', $missing->data()['errors']);

        $this->assertSame(0, Db::table('message_templates')->whereIn('code', $this->createdCodes)->count());
    }

    public function test_store_rejects_code_used_by_active_or_soft_deleted_template(): void
    {
        $active = $this->randomCode();
        $this->insertTemplate(['code' => $active]);
        $trashed = $this->randomCode();
        $this->insertTemplate(['code' => $trashed, 'deleted_at' => date('Y-m-d H:i:s')]);
        $admin = $this->actingAsAdmin(['system.message.template.create']);

        foreach ([$active, $trashed] as $code) {
            $response = $this->post(self::BASE, ['name' => '重复', 'code' => $code], $admin->token);
            $response->assertCode(422);
            $this->assertSame(lang('message.template_code_exists'), $response->data()['errors']['code'] ?? null, $code);
            $this->assertSame(1, Db::table('message_templates')->where('code', $code)->count(), '唯一索引含软删行：不能插第二行，也不能 500');
        }
    }

    public function test_update_ignores_code_and_never_overwrites_mapping_site_and_variables(): void
    {
        $code = $this->randomCode();
        $officialData = ['character_string1' => '${order_no}', 'amount2' => '${amount}元'];
        $miniData = ['thing1' => '${nickname}'];
        $variables = [['key' => 'order_no', 'name' => '订单号', 'example' => 'R20260917']];
        $id = $this->insertTemplate([
            'code'                 => $code,
            'name'                 => '旧名称',
            'remark'               => '旧备注',
            'wechat_official_data' => json_encode($officialData, JSON_UNESCAPED_UNICODE),
            'wechat_mini_data'     => json_encode($miniData, JSON_UNESCAPED_UNICODE),
            'site_enabled'         => 1,
            'site_title'           => '充值成功',
            'site_content'         => '订单 ${order_no} 已到账',
            'variables'            => json_encode($variables, JSON_UNESCAPED_UNICODE),
        ]);
        $admin = $this->actingAsAdmin(['system.message.template.list', 'system.message.template.update']);

        // 模拟编辑弹窗：拿详情整行，改表单字段后整行 PUT 回去（useFormDialog 提交的是 {...row}）
        $body = $this->get(self::BASE . "/{$id}", [], $admin->token)->assertOk()->data();
        $this->assertIsArray($body);
        $renamed = $this->rememberCode();
        $body = array_merge($body, [
            'name'                        => '新名称',
            'remark'                      => null,
            'status'                      => 0,
            'sms_enabled'                 => 1,
            'sms_template_id'             => 'SMS_NEW',
            'sms_content'                 => '新短信预览',
            'wechat_official_enabled'     => 1,
            'wechat_official_template_id' => 'tpl-new',
            'wechat_official_url'         => 'https://example.com/new',
            'wechat_mini_enabled'         => 1,
            'wechat_mini_template_id'     => 'mini-new',
            'wechat_mini_page'            => 'pages/order/detail',
            // 越权字段：code 不可改，映射、站内信、变量不在白名单
            'code'                        => $renamed,
            'wechat_official_data'        => ['thing9' => 'x'],
            'wechat_mini_data'            => ['thing9' => 'x'],
            'site_enabled'                => 0,
            'site_title'                  => '被覆盖',
            'site_content'                => '被覆盖',
            'variables'                   => [],
        ]);
        $response = $this->put(self::BASE . "/{$id}", $body, $admin->token);
        $response->assertOk();
        $this->assertSame(lang('messages.update_success'), $response->message());

        $row = Db::table('message_templates')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame($code, $row->code, 'code 不可改');
        $this->assertSame(0, Db::table('message_templates')->where('code', $renamed)->count());
        $this->assertSame(['新名称', null, 0], [$row->name, $row->remark, (int) $row->status]);
        $this->assertSame([1, 'SMS_NEW', '新短信预览'], [(int) $row->sms_enabled, $row->sms_template_id, $row->sms_content]);
        $this->assertSame([1, 'tpl-new', 'https://example.com/new'], [(int) $row->wechat_official_enabled, $row->wechat_official_template_id, $row->wechat_official_url]);
        $this->assertSame([1, 'mini-new', 'pages/order/detail'], [(int) $row->wechat_mini_enabled, $row->wechat_mini_template_id, $row->wechat_mini_page]);
        $this->assertSame(self::canonical($officialData), self::decodeColumn($row->wechat_official_data));
        $this->assertSame(self::canonical($miniData), self::decodeColumn($row->wechat_mini_data));
        $this->assertSame(self::canonical($variables), self::decodeColumn($row->variables));
        $this->assertSame([1, '充值成功', '订单 ${order_no} 已到账'], [(int) $row->site_enabled, $row->site_title, $row->site_content]);

        // 部分更新：只传 status，其它列不动
        $this->put(self::BASE . "/{$id}", ['status' => 1], $admin->token)->assertOk();
        $row = Db::table('message_templates')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame([1, '新名称', 'SMS_NEW'], [(int) $row->status, $row->name, $row->sms_template_id]);

        // 传了 name 就不能是空
        $this->put(self::BASE . "/{$id}", ['name' => ''], $admin->token)->assertCode(422);
    }

    public function test_update_returns_404_for_missing_or_soft_deleted_template(): void
    {
        $gone = $this->insertTemplate(['name' => '已删除', 'deleted_at' => date('Y-m-d H:i:s')]);
        $admin = $this->actingAsAdmin(['system.message.template.update']);

        $this->put(self::BASE . '/999999999', ['name' => '不存在'], $admin->token)->assertCode(404);
        $this->put(self::BASE . "/{$gone}", ['name' => '复活'], $admin->token)->assertCode(404);
        $this->assertSame('已删除', Db::table('message_templates')->where('id', $gone)->value('name'));
    }

    public function test_delete_soft_deletes_custom_template(): void
    {
        $id = $this->insertTemplate();
        $admin = $this->actingAsAdmin(['system.message.template.list', 'system.message.template.delete']);

        $response = $this->delete(self::BASE . "/{$id}", [], $admin->token);
        $response->assertOk();
        $this->assertSame(lang('messages.delete_success'), $response->message());
        $this->assertNotNull(Db::table('message_templates')->where('id', $id)->value('deleted_at'), '软删：行还在');

        $this->get(self::BASE . "/{$id}", [], $admin->token)->assertCode(404);
        $this->delete(self::BASE . "/{$id}", [], $admin->token)->assertCode(404);
        $this->delete(self::BASE . '/999999999', [], $admin->token)->assertCode(404);
    }

    public function test_builtin_templates_cannot_be_deleted(): void
    {
        $admin = $this->actingAsAdmin(['system.message.template.delete']);

        foreach (['user_register', 'payment_success'] as $code) {
            // Task 5 才种内置模板：种子还不存在时由夹具补一行（tearDown 按 id 删除），种子存在则直接用种子行
            $existing = Db::table('message_templates')->where('code', $code)->first();
            if ($existing === null) {
                $id = $this->insertTemplate(['code' => $code]);
            } else {
                $this->assertNull($existing->deleted_at, "{$code} 种子行不应处于软删状态");
                $id = (int) $existing->id;
            }

            try {
                $response = $this->delete(self::BASE . "/{$id}", [], $admin->token);
                $response->assertCode(400);
                $this->assertSame(lang('message.builtin_template_undeletable'), $response->message());
                $this->assertNull(Db::table('message_templates')->where('id', $id)->value('deleted_at'));
            } finally {
                // 实现有缺陷时误删了种子行：恢复，不连累其它测试
                Db::table('message_templates')->where('id', $id)->update(['deleted_at' => null]);
            }
        }
    }
}
