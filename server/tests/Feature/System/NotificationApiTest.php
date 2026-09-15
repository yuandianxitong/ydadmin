<?php

declare(strict_types=1);

namespace tests\Feature\System;

use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\TestAdmin;

final class NotificationApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/system/notification';

    private const ALL = ['system.notification.list', 'system.notification.create', 'system.notification.update', 'system.notification.delete'];

    /** @param array<string, mixed> $overrides */
    private function publish(TestAdmin $actor, array $overrides = []): int
    {
        $id = (int) $this->post(self::BASE, array_merge(['title' => '通知' . bin2hex(random_bytes(3)), 'content' => '正文', 'type' => 1], $overrides), $actor->token)->assertOk()->data()['id'];
        $this->track('notifications', $id);

        return $id;
    }

    /**
     * 直接写库造一条通知：用于接口不允许创建的形态（如 target_type=2）。
     *
     * @param array<string, mixed> $attributes
     */
    private function insertNotification(array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('notifications')->insertGetId(array_merge([
            'title'       => '直写通知',
            'content'     => '正文',
            'type'        => 1,
            'target_type' => 1,
            'status'      => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ], $attributes));
        $this->track('notifications', $id);

        return $id;
    }

    private function unread(TestAdmin $admin): int
    {
        return (int) $this->get(self::BASE . '/unread-count', [], $admin->token)->assertOk()->data()['count'];
    }

    /**
     * @param array<string, mixed> $query
     * @return list<int>
     */
    private function mineIds(TestAdmin $admin, array $query = []): array
    {
        return array_map('intval', array_column($this->get(self::BASE . '/mine', $query + ['limit' => 100], $admin->token)->assertOk()->data()['list'], 'id'));
    }

    public function test_menu_seeds(): void
    {
        $menus = Db::table('menus')->whereBetween('id', [80, 83])->orderBy('id')->get(['id', 'parent_id', 'type', 'permission'])->all();
        $this->assertSame(
            [[80, 2, 2, 'system.notification.list'], [81, 80, 3, 'system.notification.create'], [82, 80, 3, 'system.notification.update'], [83, 80, 3, 'system.notification.delete']],
            array_map(static fn (object $menu): array => [(int) $menu->id, (int) $menu->parent_id, (int) $menu->type, (string) $menu->permission], $menus)
        );
        $this->assertSame('/system/notification/index', Db::table('menus')->where('id', 80)->value('component'));
    }

    public function test_store_records_the_acting_admin_as_sender(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);

        $response = $this->post(self::BASE, ['title' => '发布测试', 'content' => '正文', 'type' => 2, 'sender_id' => 999999], $admin->token)->assertOk();
        $this->assertSame(lang('messages.publish_success'), $response->message());
        $id = (int) $response->data()['id'];
        $this->track('notifications', $id);

        $row = Db::table('notifications')->where('id', $id)->first();
        $this->assertSame($admin->id, (int) $row->sender_id, 'sender_id 取当前管理员，不接收请求体');
        $this->assertSame(2, (int) $row->type);
        $this->assertSame(1, (int) $row->target_type, 'target_type 缺省为全员广播');
        $this->assertSame(1, (int) $row->status);
    }

    public function test_target_type_outside_one_and_two_is_rejected(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $before = Db::table('notifications')->count();

        $response = $this->post(self::BASE, ['title' => '非法目标', 'content' => '正文', 'type' => 1, 'target_type' => 3], $admin->token)->assertCode(422);
        $this->assertSame(lang('validation.notification_target_invalid'), $response->data()['errors']['target_type']);
        $this->assertSame($before, Db::table('notifications')->count());
    }

    public function test_validation(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);

        $this->assertSame(lang('validation.notification_title_require'), $this->post(self::BASE, ['content' => '正文', 'type' => 1], $admin->token)->assertCode(422)->data()['errors']['title']);
        $this->assertSame(lang('validation.notification_type_invalid'), $this->post(self::BASE, ['title' => '标题', 'content' => '正文', 'type' => 4], $admin->token)->assertCode(422)->data()['errors']['type']);
        $this->assertSame(lang('validation.notification_content_max'), $this->post(self::BASE, ['title' => '标题', 'content' => str_repeat('长', 10001), 'type' => 1], $admin->token)->assertCode(422)->data()['errors']['content']);

        $id = $this->publish($admin, ['title' => '原标题']);
        foreach (['title', 'content', 'type', 'status'] as $field) {
            $response = $this->put(self::BASE . "/{$id}", [$field => ''], $admin->token);
            $response->assertCode(422);
            $this->assertArrayHasKey($field, $response->data()['errors']);
        }
        $row = Db::table('notifications')->where('id', $id)->first();
        $this->assertSame('原标题', $row->title);
        $this->assertSame(1, (int) $row->status);
    }

    public function test_index_has_reads_count_and_filters_with_literal_wildcards(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $reader = $this->actingAsAdmin();
        $tag = bin2hex(random_bytes(3));
        $percent = $this->publish($admin, ['title' => "{$tag}100%", 'type' => 2]);
        $plain = $this->publish($admin, ['title' => "{$tag}1000", 'type' => 3]);
        $this->post(self::BASE . "/{$percent}/read", [], $reader->token)->assertOk();

        $data = $this->get(self::BASE, ['keyword' => $tag], $admin->token)->assertOk()->data();
        $this->assertSame(2, $data['pagination']['total']);
        $counts = array_column($data['list'], 'reads_count', 'id');
        $this->assertSame(1, (int) $counts[$percent]);
        $this->assertSame(0, (int) $counts[$plain]);

        $this->assertSame([$percent], array_column($this->get(self::BASE, ['keyword' => "{$tag}100%"], $admin->token)->assertOk()->data()['list'], 'id'), '% 按字面匹配（core\support\Like）');
        $this->assertSame([$plain], array_column($this->get(self::BASE, ['keyword' => $tag, 'type' => 3], $admin->token)->assertOk()->data()['list'], 'id'));
    }

    public function test_show_update_and_delete(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $id = $this->publish($admin, ['title' => '原标题']);

        $this->assertSame('原标题', $this->get(self::BASE . "/{$id}", [], $admin->token)->assertOk()->data()['title']);
        $this->put(self::BASE . "/{$id}", ['title' => '新标题', 'status' => 0], $admin->token)->assertOk();
        $row = Db::table('notifications')->where('id', $id)->first();
        $this->assertSame('新标题', $row->title);
        $this->assertSame(0, (int) $row->status);

        $notFound = lang('business.notification_not_found');
        $this->assertSame($notFound, $this->get(self::BASE . '/999999', [], $admin->token)->assertCode(400)->message());
        $this->assertSame($notFound, $this->put(self::BASE . '/999999', ['title' => 'x'], $admin->token)->assertCode(400)->message());
        $this->assertSame($notFound, $this->delete(self::BASE . '/999999', [], $admin->token)->assertCode(400)->message());

        $this->delete(self::BASE . "/{$id}", [], $admin->token)->assertOk();
        $this->assertNotNull(Db::table('notifications')->where('id', $id)->value('deleted_at'));
        $this->assertSame($notFound, $this->get(self::BASE . "/{$id}", [], $admin->token)->assertCode(400)->message());
    }

    public function test_mine_lists_published_broadcasts_with_read_flag(): void
    {
        $publisher = $this->actingAsAdmin(self::ALL);
        $me = $this->actingAsAdmin();
        $read = $this->publish($publisher);
        $unread = $this->publish($publisher);
        $draft = $this->publish($publisher, ['status' => 0]);
        $targeted = $this->insertNotification(['target_type' => 2]);
        $deleted = $this->publish($publisher);
        $this->delete(self::BASE . "/{$deleted}", [], $publisher->token)->assertOk();

        $ids = $this->mineIds($me);
        $this->assertContains($read, $ids);
        $this->assertContains($unread, $ids);
        foreach ([$draft, $targeted, $deleted] as $hidden) {
            $this->assertNotContains($hidden, $ids);
        }

        $this->post(self::BASE . "/{$read}/read", [], $me->token)->assertOk();
        $flags = array_column($this->get(self::BASE . '/mine', ['limit' => 100], $me->token)->assertOk()->data()['list'], 'is_read', 'id');
        $this->assertTrue($flags[$read]);
        $this->assertFalse($flags[$unread]);

        $this->assertContains($read, $this->mineIds($me, ['is_read' => 1]));
        $this->assertNotContains($unread, $this->mineIds($me, ['is_read' => 1]));
        $this->assertContains($unread, $this->mineIds($me, ['is_read' => 0]));
        $this->assertNotContains($read, $this->mineIds($me, ['is_read' => 0]));
        $this->get(self::BASE . '/mine', ['is_read' => 2], $me->token)->assertCode(422);
    }

    public function test_unread_count_and_idempotent_read(): void
    {
        $publisher = $this->actingAsAdmin(self::ALL);
        $me = $this->actingAsAdmin();
        $before = $this->unread($me);

        $a = $this->publish($publisher);
        $this->publish($publisher);
        $this->publish($publisher, ['status' => 0]);
        $this->insertNotification(['target_type' => 2]);
        $this->assertSame($before + 2, $this->unread($me), '草稿与指定用户通知不计入未读');

        $this->post(self::BASE . "/{$a}/read", [], $me->token)->assertOk();
        $this->assertSame($before + 1, $this->unread($me));

        // 幂等：再次标记不新增行，也不覆盖第一次的 read_at
        $where = ['notification_id' => $a, 'admin_id' => $me->id];
        Db::table('notification_reads')->where($where)->update(['read_at' => '2020-01-01 00:00:00']);
        $this->post(self::BASE . "/{$a}/read", [], $me->token)->assertOk();
        $this->assertSame(1, Db::table('notification_reads')->where($where)->count());
        $this->assertSame('2020-01-01 00:00:00', (string) Db::table('notification_reads')->where($where)->value('read_at'));
        $this->assertSame($before + 1, $this->unread($me));
    }

    public function test_read_rejects_notifications_outside_the_personal_view(): void
    {
        $publisher = $this->actingAsAdmin(self::ALL);
        $me = $this->actingAsAdmin();
        $draft = $this->publish($publisher, ['status' => 0]);
        $targeted = $this->insertNotification(['target_type' => 2]);

        foreach ([$draft, $targeted, 999999] as $id) {
            $this->assertSame(lang('business.notification_not_found'), $this->post(self::BASE . "/{$id}/read", [], $me->token)->assertCode(400)->message());
        }
        $this->assertSame(0, Db::table('notification_reads')->where('admin_id', $me->id)->count(), '不可见的通知不写已读记录');
    }

    public function test_read_all_only_touches_the_current_admin(): void
    {
        $publisher = $this->actingAsAdmin(self::ALL);
        $me = $this->actingAsAdmin();
        $other = $this->actingAsAdmin();
        $a = $this->publish($publisher);
        $b = $this->publish($publisher);
        // 已有行但 read_at 为空：read-all 要补上时间，而不是撞唯一键
        Db::table('notification_reads')->insert(['notification_id' => $b, 'admin_id' => $me->id, 'read_at' => null, 'created_at' => date('Y-m-d H:i:s')]);
        $otherBefore = $this->unread($other);

        $this->post(self::BASE . '/read-all', [], $me->token)->assertOk();

        $this->assertSame(0, $this->unread($me));
        $mine = Db::table('notification_reads')->where('admin_id', $me->id)->whereIn('notification_id', [$a, $b]);
        $this->assertSame(2, (clone $mine)->count());
        $this->assertSame(0, (clone $mine)->whereNull('read_at')->count());
        $this->assertSame($otherBefore, $this->unread($other), '只标记当前管理员');

        $this->post(self::BASE . '/read-all', [], $me->token)->assertOk();
        $this->assertSame(0, $this->unread($me));
    }

    public function test_personal_endpoints_need_login_only(): void
    {
        $nobody = $this->actingAsAdmin();

        $this->get(self::BASE, [], $nobody->token)->assertCode(403);
        $this->post(self::BASE, ['title' => '越权', 'content' => '正文', 'type' => 1], $nobody->token)->assertCode(403);
        $this->get(self::BASE . '/mine', [], $nobody->token)->assertOk();
        $this->get(self::BASE . '/unread-count', [], $nobody->token)->assertOk();
        $this->post(self::BASE . '/read-all', [], $nobody->token)->assertOk();
        $this->get(self::BASE . '/mine')->assertCode(401);
    }
}
