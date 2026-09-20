<?php

declare(strict_types=1);

namespace tests\Feature\Wechat;

use app\repository\wechat\WechatAutoReplyRepository;
use support\Db;
use tests\Support\ApiTestCase;

final class AutoReplyApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/wechat/auto-reply';

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'channel.official.auto_reply',
            'channel.official.auto_reply.create',
            'channel.official.auto_reply.update',
            'channel.official.auto_reply.delete',
        ] as $permission) {
            if (Db::table('menus')->where('permission', $permission)->exists()) {
                continue;
            }
            $id = (int) Db::table('menus')->insertGetId([
                'parent_id' => 0,
                'type' => 3,
                'title' => '自动回复测试权限',
                'permission' => $permission,
            ]);
            $this->track('menus', $id);
        }
    }

    protected function tearDown(): void
    {
        Db::table('wechat_auto_replies')->delete();
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function insertRule(array $attributes = []): int
    {
        $row = (new WechatAutoReplyRepository())->create(array_merge([
            'type'       => WechatAutoReplyRepository::TYPE_KEYWORD,
            'keyword'    => 'hello',
            'match_type' => WechatAutoReplyRepository::MATCH_EXACT,
            'content'    => 'reply',
            'status'     => WechatAutoReplyRepository::STATUS_ENABLED,
            'sort_order' => 0,
        ], $attributes));

        return (int) $row['id'];
    }

    public function test_every_endpoint_requires_its_permission(): void
    {
        $id = $this->insertRule();
        $nobody = $this->actingAsAdmin();

        $this->get(self::BASE, [], $nobody->token)->assertCode(403);
        $this->get(self::BASE . "/{$id}", [], $nobody->token)->assertCode(403);
        $this->post(self::BASE, ['type' => 'keyword', 'keyword' => 'x', 'content' => 'y'], $nobody->token)->assertCode(403);
        $this->put(self::BASE . "/{$id}", ['content' => 'y'], $nobody->token)->assertCode(403);
        $this->delete(self::BASE . "/{$id}", [], $nobody->token)->assertCode(403);

        $reader = $this->actingAsAdmin(['channel.official.auto_reply']);
        $this->get(self::BASE, [], $reader->token)->assertOk();
        $this->get(self::BASE . "/{$id}", [], $reader->token)->assertOk();
        $this->put(self::BASE . "/{$id}", ['content' => 'y'], $reader->token)->assertCode(403);
    }

    public function test_list_returns_standard_pagination(): void
    {
        $this->insertRule();
        $admin = $this->actingAsAdmin(['channel.official.auto_reply']);

        $data = $this->get(self::BASE, ['page' => 1, 'limit' => 10], $admin->token)->assertOk()->data();

        $this->assertSame(['list', 'pagination'], array_keys($data));
        $this->assertSame(['current_page', 'per_page', 'total', 'last_page'], array_keys($data['pagination']));
    }

    public function test_store_validates_content_and_forces_text_reply_type(): void
    {
        $admin = $this->actingAsAdmin(['channel.official.auto_reply.create']);
        $invalid = $this->post(self::BASE, ['type' => 'keyword', 'keyword' => 'hi'], $admin->token);
        $invalid->assertCode(422);
        $this->assertArrayHasKey('content', $invalid->data()['errors']);

        $this->post(self::BASE, [
            'type' => 'keyword',
            'keyword' => 'hi',
            'content' => 'yo',
        ], $admin->token)->assertOk();
        $this->post(self::BASE, [
            'type' => 'keyword',
            'keyword' => 'hello',
            'content' => 'world',
            'reply_type' => 'news',
        ], $admin->token)->assertOk();

        $this->assertSame(['text', 'text'], Db::table('wechat_auto_replies')->orderBy('id')->pluck('reply_type')->all());
    }

    public function test_update_rejects_second_enabled_subscribe(): void
    {
        $this->insertRule(['type' => 'subscribe', 'keyword' => '']);
        $disabled = $this->insertRule(['type' => 'subscribe', 'keyword' => '', 'status' => 0]);
        $admin = $this->actingAsAdmin(['channel.official.auto_reply.update']);

        $response = $this->put(self::BASE . "/{$disabled}", ['status' => 1], $admin->token);

        $response->assertCode(400);
        $this->assertSame(lang('wechat.reply_exists_subscribe'), $response->message());
    }

    public function test_delete_makes_detail_unavailable(): void
    {
        $id = $this->insertRule();
        $admin = $this->actingAsAdmin([
            'channel.official.auto_reply',
            'channel.official.auto_reply.delete',
        ]);

        $this->delete(self::BASE . "/{$id}", [], $admin->token)->assertOk();
        $this->get(self::BASE . "/{$id}", [], $admin->token)->assertCode(400);
    }
}
