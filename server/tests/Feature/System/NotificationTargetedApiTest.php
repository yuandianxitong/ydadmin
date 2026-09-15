<?php

declare(strict_types=1);

namespace tests\Feature\System;

use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\TestAdmin;

/**
 * spec §6「通知 target_type=2」：收件人存进 notification_reads（read_at 为 NULL 即未读），
 * 个人侧可见 = 已发布 且（全员广播 或 存在本人收件行）。
 */
final class NotificationTargetedApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/system/notification';

    private const ALL = ['system.notification.list', 'system.notification.create', 'system.notification.update', 'system.notification.delete'];

    /** @param list<int> $adminIds */
    private function publishTargeted(TestAdmin $actor, array $adminIds, array $overrides = []): int
    {
        $id = (int) $this->post(self::BASE, array_merge([
            'title'       => '指定' . bin2hex(random_bytes(3)),
            'content'     => '正文',
            'type'        => 1,
            'target_type' => 2,
            'admin_ids'   => $adminIds,
        ], $overrides), $actor->token)->assertOk()->data()['id'];
        $this->track('notifications', $id);

        return $id;
    }

    /** @return list<int> */
    private function recipients(int $notificationId): array
    {
        $ids = array_map('intval', Db::table('notification_reads')->where('notification_id', $notificationId)->pluck('admin_id')->all());
        sort($ids);

        return $ids;
    }

    /** @return list<int> */
    private function mineIds(TestAdmin $admin): array
    {
        return array_map('intval', array_column($this->get(self::BASE . '/mine', ['limit' => 100], $admin->token)->assertOk()->data()['list'], 'id'));
    }

    private function unread(TestAdmin $admin): int
    {
        return (int) $this->get(self::BASE . '/unread-count', [], $admin->token)->assertOk()->data()['count'];
    }

    protected function tearDown(): void
    {
        // 收件行随通知清理：ApiTestCase 只按 admin_id 清 notification_reads，这里按本类建的通知兜底
        Db::table('notification_reads')->whereNotIn('notification_id', Db::table('notifications')->pluck('id'))->delete();
        parent::tearDown();
    }

    public function test_store_writes_unread_recipient_rows_for_deduplicated_ids(): void
    {
        $publisher = $this->actingAsAdmin(self::ALL);
        $a = $this->actingAsAdmin();
        $b = $this->actingAsAdmin();

        $id = $this->publishTargeted($publisher, [$a->id, $b->id, $a->id]);

        $this->assertSame(2, (int) Db::table('notifications')->where('id', $id)->value('target_type'));
        $expected = [$a->id, $b->id];
        sort($expected);
        $this->assertSame($expected, $this->recipients($id));
        $this->assertSame(0, Db::table('notification_reads')->where('notification_id', $id)->whereNotNull('read_at')->count(), '收件行初始都是未读');
    }

    public function test_only_recipients_see_it_in_mine_and_unread_count(): void
    {
        $publisher = $this->actingAsAdmin(self::ALL);
        $recipient = $this->actingAsAdmin();
        $outsider = $this->actingAsAdmin();
        $recipientBefore = $this->unread($recipient);
        $outsiderBefore = $this->unread($outsider);

        $id = $this->publishTargeted($publisher, [$recipient->id]);

        $this->assertContains($id, $this->mineIds($recipient));
        $this->assertNotContains($id, $this->mineIds($outsider));
        $this->assertSame($recipientBefore + 1, $this->unread($recipient));
        $this->assertSame($outsiderBefore, $this->unread($outsider));

        $flags = array_column($this->get(self::BASE . '/mine', ['limit' => 100], $recipient->token)->assertOk()->data()['list'], 'is_read', 'id');
        $this->assertFalse($flags[$id], '未读收件行不算已读');
    }

    public function test_draft_targeted_notification_is_hidden_from_recipients(): void
    {
        $publisher = $this->actingAsAdmin(self::ALL);
        $recipient = $this->actingAsAdmin();

        $id = $this->publishTargeted($publisher, [$recipient->id], ['status' => 0]);

        $this->assertNotContains($id, $this->mineIds($recipient));
        $this->assertSame([$recipient->id], $this->recipients($id), '草稿也写收件行，发布后即可见');
    }

    public function test_read_marks_the_recipient_row_and_outsiders_get_not_found(): void
    {
        $publisher = $this->actingAsAdmin(self::ALL);
        $recipient = $this->actingAsAdmin();
        $outsider = $this->actingAsAdmin();
        $id = $this->publishTargeted($publisher, [$recipient->id]);

        $this->post(self::BASE . "/{$id}/read", [], $recipient->token)->assertOk();
        $this->assertNotNull(Db::table('notification_reads')->where(['notification_id' => $id, 'admin_id' => $recipient->id])->value('read_at'));
        $this->assertSame(1, Db::table('notification_reads')->where(['notification_id' => $id, 'admin_id' => $recipient->id])->count());

        $this->assertSame(lang('business.notification_not_found'), $this->post(self::BASE . "/{$id}/read", [], $outsider->token)->assertCode(400)->message());
        $this->assertSame(0, Db::table('notification_reads')->where(['notification_id' => $id, 'admin_id' => $outsider->id])->count(), '非收件人不写已读行');
    }

    public function test_read_all_marks_targeted_rows_and_never_touches_invisible_ones(): void
    {
        $publisher = $this->actingAsAdmin(self::ALL);
        $me = $this->actingAsAdmin();
        $other = $this->actingAsAdmin();
        $mine = $this->publishTargeted($publisher, [$me->id]);
        $notMine = $this->publishTargeted($publisher, [$other->id]);

        $this->post(self::BASE . '/read-all', [], $me->token)->assertOk();

        $this->assertSame(0, $this->unread($me));
        $this->assertNotNull(Db::table('notification_reads')->where(['notification_id' => $mine, 'admin_id' => $me->id])->value('read_at'));
        $this->assertSame(0, Db::table('notification_reads')->where(['notification_id' => $notMine, 'admin_id' => $me->id])->count(), '不可见的指定通知不插入已读行');
        $this->assertNull(Db::table('notification_reads')->where(['notification_id' => $notMine, 'admin_id' => $other->id])->value('read_at'), '别人的收件行不被标记');
    }

    public function test_admin_ids_validation(): void
    {
        $publisher = $this->actingAsAdmin(self::ALL);
        $before = Db::table('notifications')->count();

        $response = $this->post(self::BASE, ['title' => '缺名单', 'content' => '正文', 'type' => 1, 'target_type' => 2], $publisher->token)->assertCode(422);
        $this->assertSame(lang('validation.notification_admin_ids_require'), $response->data()['errors']['admin_ids']);

        $response = $this->post(self::BASE, ['title' => '超上限', 'content' => '正文', 'type' => 1, 'target_type' => 2, 'admin_ids' => range(1, 501)], $publisher->token)->assertCode(422);
        $this->assertSame(lang('validation.notification_admin_ids_max'), $response->data()['errors']['admin_ids']);

        $response = $this->post(self::BASE, ['title' => '不存在', 'content' => '正文', 'type' => 1, 'target_type' => 2, 'admin_ids' => [999999999]], $publisher->token)->assertCode(422);
        $this->assertSame(lang('validation.notification_admin_ids_invalid'), $response->data()['errors']['admin_ids']);

        $this->assertSame($before, Db::table('notifications')->count(), '校验失败不写通知');
    }

    public function test_admin_ids_outside_the_data_scope_are_rejected(): void
    {
        // data_scope=4「仅本人」：只能看到自己
        $scoped = $this->actingAsAdmin(self::ALL, [], ['data_scope' => 4]);
        $stranger = $this->actingAsAdmin();

        $response = $this->post(self::BASE, ['title' => '越界', 'content' => '正文', 'type' => 1, 'target_type' => 2, 'admin_ids' => [$scoped->id, $stranger->id]], $scoped->token)->assertCode(422);
        $this->assertSame(lang('validation.notification_admin_ids_invalid'), $response->data()['errors']['admin_ids']);

        $id = $this->publishTargeted($scoped, [$scoped->id]);
        $this->assertSame([$scoped->id], $this->recipients($id), '范围内的名单照常写入');
    }

    public function test_broadcast_ignores_admin_ids(): void
    {
        $publisher = $this->actingAsAdmin(self::ALL);
        $someone = $this->actingAsAdmin();

        $id = (int) $this->post(self::BASE, ['title' => '广播', 'content' => '正文', 'type' => 1, 'target_type' => 1, 'admin_ids' => [$someone->id]], $publisher->token)->assertOk()->data()['id'];
        $this->track('notifications', $id);

        $this->assertSame([], $this->recipients($id));
    }

    public function test_update_cannot_change_target_type(): void
    {
        $publisher = $this->actingAsAdmin(self::ALL);
        $recipient = $this->actingAsAdmin();
        $targeted = $this->publishTargeted($publisher, [$recipient->id]);
        $broadcast = (int) $this->post(self::BASE, ['title' => '广播', 'content' => '正文', 'type' => 1], $publisher->token)->assertOk()->data()['id'];
        $this->track('notifications', $broadcast);

        $response = $this->put(self::BASE . "/{$targeted}", ['target_type' => 1], $publisher->token)->assertCode(422);
        $this->assertSame(lang('validation.notification_target_immutable'), $response->data()['errors']['target_type']);
        $response = $this->put(self::BASE . "/{$broadcast}", ['target_type' => 2, 'admin_ids' => [$recipient->id]], $publisher->token)->assertCode(422);
        $this->assertSame(lang('validation.notification_target_immutable'), $response->data()['errors']['target_type']);

        // 与原值相同（前端编辑表单会原样回传 target_type）照常通过
        $this->put(self::BASE . "/{$targeted}", ['target_type' => 2, 'title' => '改标题'], $publisher->token)->assertOk();
        $this->assertSame('改标题', Db::table('notifications')->where('id', $targeted)->value('title'));
        $this->assertSame([], $this->recipients($broadcast));
    }

    public function test_update_replaces_unread_recipients_and_keeps_read_ones(): void
    {
        $publisher = $this->actingAsAdmin(self::ALL);
        $readA = $this->actingAsAdmin();
        $unreadB = $this->actingAsAdmin();
        $newC = $this->actingAsAdmin();
        $id = $this->publishTargeted($publisher, [$readA->id, $unreadB->id]);
        $this->post(self::BASE . "/{$id}/read", [], $readA->token)->assertOk();

        $this->put(self::BASE . "/{$id}", ['admin_ids' => [$newC->id]], $publisher->token)->assertOk();

        $expected = [$readA->id, $newC->id];
        sort($expected);
        $this->assertSame($expected, $this->recipients($id), 'B 未读被移除，A 已读保留，C 新增');
        $this->assertNotContains($id, $this->mineIds($unreadB));
        $this->assertContains($id, $this->mineIds($newC));
    }

    public function test_index_has_target_count_and_show_has_admin_ids(): void
    {
        $publisher = $this->actingAsAdmin(self::ALL);
        $a = $this->actingAsAdmin();
        $b = $this->actingAsAdmin();
        $tag = bin2hex(random_bytes(3));
        $targeted = $this->publishTargeted($publisher, [$a->id, $b->id], ['title' => "{$tag}指定"]);
        $broadcast = (int) $this->post(self::BASE, ['title' => "{$tag}广播", 'content' => '正文', 'type' => 1], $publisher->token)->assertOk()->data()['id'];
        $this->track('notifications', $broadcast);
        $this->post(self::BASE . "/{$targeted}/read", [], $a->token)->assertOk();

        $rows = array_column($this->get(self::BASE, ['keyword' => $tag], $publisher->token)->assertOk()->data()['list'], null, 'id');
        $this->assertSame(2, $rows[$targeted]['target_count']);
        $this->assertSame(1, (int) $rows[$targeted]['reads_count'], 'reads_count 仍是已读人数，不是收件人数');
        $this->assertNull($rows[$broadcast]['target_count']);
        $this->assertArrayNotHasKey('recipients_count', $rows[$targeted], '内部计数别名不外泄');

        $detail = $this->get(self::BASE . "/{$targeted}", [], $publisher->token)->assertOk()->data();
        $expected = [$a->id, $b->id];
        sort($expected);
        $this->assertSame($expected, $detail['admin_ids']);
        $this->assertSame([], $this->get(self::BASE . "/{$broadcast}", [], $publisher->token)->assertOk()->data()['admin_ids']);
    }

    public function test_admin_options_respects_scope_keyword_status_and_limit(): void
    {
        $tag = 'opt' . bin2hex(random_bytes(3));
        $publisher = $this->actingAsAdmin(self::ALL);
        $enabled = $this->actingAsAdmin([], ['username' => "{$tag}_on", 'nickname' => "昵称{$tag}"]);
        $disabled = $this->actingAsAdmin([], ['username' => "{$tag}_off", 'status' => 0]);

        $list = $this->get(self::BASE . '/admin-options', ['keyword' => $tag], $publisher->token)->assertOk()->data();
        $ids = array_map('intval', array_column($list, 'id'));
        $this->assertContains($enabled->id, $ids);
        $this->assertNotContains($disabled->id, $ids, '只列启用的管理员');
        $row = array_values(array_filter($list, static fn (array $r): bool => (int) $r['id'] === $enabled->id))[0];
        $this->assertSame(['id', 'username', 'nickname'], array_keys($row));

        $this->assertContains($enabled->id, array_map('intval', array_column($this->get(self::BASE . '/admin-options', ['keyword' => "昵称{$tag}"], $publisher->token)->assertOk()->data(), 'id')), '昵称也参与模糊匹配');
        $this->assertLessThanOrEqual(50, count($this->get(self::BASE . '/admin-options', [], $publisher->token)->assertOk()->data()));

        $scoped = $this->actingAsAdmin(self::ALL, [], ['data_scope' => 4]);
        $this->assertSame([$scoped->id], array_map('intval', array_column($this->get(self::BASE . '/admin-options', [], $scoped->token)->assertOk()->data(), 'id')), '「仅本人」只能选到自己');

        $this->get(self::BASE . '/admin-options', [], $this->actingAsAdmin(['system.notification.list'])->token)->assertCode(403);
    }
}
