<?php

declare(strict_types=1);

namespace tests\Feature\Message;

use support\Db;
use tests\Support\ApiTestCase;

/** 管理端消息日志列表（M6b spec §4.2）：channel、status、receiver（LIKE）、template_code 筛选，id 倒序。 */
final class MessageLogApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/message/log';

    /** @param array<string, mixed> $attributes */
    private function insertLog(string $templateCode, array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('message_logs')->insertGetId(array_merge([
            'template_id'   => null,
            'template_code' => $templateCode,
            'channel'       => 'sms',
            'user_id'       => null,
            'receiver'      => '',
            'variables'     => null,
            'content'       => null,
            'status'        => 0,
            'error_msg'     => '',
            'attempts'      => 0,
            'sent_at'       => null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ], $attributes));
        $this->track('message_logs', $id);

        return $id;
    }

    private static function tag(): string
    {
        return 'log_' . bin2hex(random_bytes(6));
    }

    /**
     * @param array<string, mixed> $query
     * @return list<int>
     */
    private function ids(array $query, string $token): array
    {
        $data = $this->get(self::BASE, $query + ['page' => 1, 'limit' => 100], $token)->assertOk()->data();

        return array_map('intval', array_column($data['list'], 'id'));
    }

    public function test_list_requires_its_own_permission(): void
    {
        $nobody = $this->actingAsAdmin();
        $this->get(self::BASE, [], $nobody->token)->assertCode(403);

        $templateReader = $this->actingAsAdmin(['system.message.template.list']);
        $this->get(self::BASE, [], $templateReader->token)->assertCode(403);

        $logReader = $this->actingAsAdmin(['system.message.log.list']);
        $this->get(self::BASE, [], $logReader->token)->assertOk();
    }

    public function test_list_filters_by_template_code_channel_status_and_receiver(): void
    {
        $tag = self::tag();
        $digits = sprintf('%04d', random_int(0, 9999));
        $openidHead = 'o' . bin2hex(random_bytes(3));
        $sms = $this->insertLog($tag, ['channel' => 'sms', 'status' => 1, 'receiver' => "138****{$digits}", 'sent_at' => date('Y-m-d H:i:s')]);
        $official = $this->insertLog($tag, ['channel' => 'wechat_official', 'status' => 2, 'receiver' => "{$openidHead}…", 'error_msg' => 'wechat errcode 43004']);
        $mini = $this->insertLog($tag, ['channel' => 'wechat_mini', 'status' => 0, 'receiver' => 'oZZZZZ…']);
        $site = $this->insertLog($tag, ['channel' => 'site', 'status' => 1, 'receiver' => 'user#' . random_int(1, 999999)]);
        $this->insertLog($tag . '_other', ['channel' => 'sms', 'status' => 1, 'receiver' => "138****{$digits}"]);
        $admin = $this->actingAsAdmin(['system.message.log.list']);

        $this->assertSame([$site, $mini, $official, $sms], $this->ids(['template_code' => $tag], $admin->token), 'template_code 精确匹配，id 倒序');
        $this->assertSame([$sms], $this->ids(['template_code' => $tag, 'channel' => 'sms'], $admin->token));
        $this->assertSame([$site], $this->ids(['template_code' => $tag, 'channel' => 'site'], $admin->token));
        $this->assertSame([$mini], $this->ids(['template_code' => $tag, 'status' => 0], $admin->token), 'status=0 是有效筛选');
        $this->assertSame([$site, $sms], $this->ids(['template_code' => $tag, 'status' => 1], $admin->token));
        $this->assertSame([$official], $this->ids(['template_code' => $tag, 'channel' => 'wechat_official', 'status' => 2], $admin->token));
        $this->assertSame([$sms], $this->ids(['template_code' => $tag, 'receiver' => "****{$digits}"], $admin->token), 'receiver 对遮蔽值做 LIKE');
        $this->assertSame([$official], $this->ids(['template_code' => $tag, 'receiver' => $openidHead], $admin->token));

        // 前端默认带 channel=''、receiver=''：等同不筛选
        $this->assertSame([$site, $mini, $official, $sms], $this->ids(['template_code' => $tag, 'channel' => '', 'receiver' => ''], $admin->token));

        $row = $this->get(self::BASE, ['template_code' => $tag, 'channel' => 'wechat_official'], $admin->token)->assertOk()->data()['list'][0];
        foreach (['id', 'template_code', 'channel', 'receiver', 'status', 'error_msg', 'sent_at', 'created_at'] as $field) {
            $this->assertArrayHasKey($field, $row, "日志页读取 {$field}");
        }
        $this->assertSame('wechat errcode 43004', $row['error_msg']);
    }

    public function test_list_is_id_desc_with_standard_pagination(): void
    {
        $tag = self::tag();
        $first = $this->insertLog($tag);
        $this->insertLog($tag);
        $this->insertLog($tag);
        $admin = $this->actingAsAdmin(['system.message.log.list']);

        $data = $this->get(self::BASE, ['template_code' => $tag, 'page' => 2, 'limit' => 2], $admin->token)->assertOk()->data();

        $this->assertSame(['list', 'pagination'], array_keys($data));
        $this->assertSame(['current_page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2], $data['pagination']);
        $this->assertSame([$first], array_map('intval', array_column($data['list'], 'id')));
    }

    public function test_list_rejects_unknown_channel_and_status(): void
    {
        $admin = $this->actingAsAdmin(['system.message.log.list']);

        $this->assertArrayHasKey('channel', $this->get(self::BASE, ['channel' => 'email'], $admin->token)->assertCode(422)->data()['errors']);
        $this->assertArrayHasKey('status', $this->get(self::BASE, ['status' => 3], $admin->token)->assertCode(422)->data()['errors']);
    }
}
