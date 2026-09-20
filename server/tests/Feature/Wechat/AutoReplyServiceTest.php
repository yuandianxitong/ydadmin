<?php

declare(strict_types=1);

namespace tests\Feature\Wechat;

use app\repository\wechat\WechatAutoReplyRepository;
use app\service\wechat\AutoReplyService;
use core\exception\BusinessException;
use support\Container;
use support\Db;
use tests\TestCase;

final class AutoReplyServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Db::table('wechat_auto_replies')->delete();
        parent::tearDown();
    }

    private function service(): AutoReplyService
    {
        return Container::get(AutoReplyService::class);
    }

    /** @param array<string, mixed> $attributes */
    private function createRule(array $attributes): int
    {
        $repo = new WechatAutoReplyRepository();
        $row = $repo->create(array_merge([
            'type'       => WechatAutoReplyRepository::TYPE_KEYWORD,
            'keyword'    => 'hello',
            'match_type' => WechatAutoReplyRepository::MATCH_EXACT,
            'content'    => 'reply',
            'status'     => WechatAutoReplyRepository::STATUS_ENABLED,
            'sort_order' => 0,
        ], $attributes));

        return (int) $row['id'];
    }

    public function test_exact_keyword_wins_over_fuzzy_keyword(): void
    {
        $this->createRule(['keyword' => 'hel', 'match_type' => WechatAutoReplyRepository::MATCH_FUZZY, 'content' => 'fuzzy']);
        $this->createRule(['keyword' => 'hello', 'content' => 'exact', 'sort_order' => 99]);

        $this->assertSame('exact', $this->service()->matchKeyword('hello'));
    }

    public function test_fuzzy_keyword_uses_sort_order_and_skips_empty_keyword(): void
    {
        $this->createRule(['keyword' => 'ell', 'match_type' => WechatAutoReplyRepository::MATCH_FUZZY, 'content' => 'second', 'sort_order' => 2]);
        $this->createRule(['keyword' => 'he', 'match_type' => WechatAutoReplyRepository::MATCH_FUZZY, 'content' => 'first', 'sort_order' => 1]);
        $this->createRule(['keyword' => '', 'match_type' => WechatAutoReplyRepository::MATCH_FUZZY, 'content' => 'empty', 'sort_order' => 0]);

        $this->assertSame('first', $this->service()->matchKeyword('hello'));
    }

    public function test_keyword_falls_back_to_default_or_null(): void
    {
        $this->assertNull($this->service()->matchKeyword('missing'));
        $this->createRule([
            'type' => WechatAutoReplyRepository::TYPE_DEFAULT,
            'keyword' => '',
            'content' => 'fallback',
        ]);

        $this->assertSame('fallback', $this->service()->matchKeyword('missing'));
    }

    public function test_subscribe_reply_returns_enabled_content_or_null(): void
    {
        $this->assertNull($this->service()->subscribeReply());
        $this->createRule([
            'type' => WechatAutoReplyRepository::TYPE_SUBSCRIBE,
            'keyword' => '',
            'content' => 'welcome',
        ]);

        $this->assertSame('welcome', $this->service()->subscribeReply());
    }

    public function test_create_rejects_second_enabled_subscribe(): void
    {
        $this->createRule(['type' => WechatAutoReplyRepository::TYPE_SUBSCRIBE, 'keyword' => 'ignored']);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(lang('wechat.reply_exists_subscribe'));
        $this->service()->create([
            'type' => WechatAutoReplyRepository::TYPE_SUBSCRIBE,
            'keyword' => 'also ignored',
            'match_type' => WechatAutoReplyRepository::MATCH_FUZZY,
            'content' => 'another',
            'status' => 1,
        ]);
    }

    public function test_update_rejects_enabling_second_default(): void
    {
        $this->createRule(['type' => WechatAutoReplyRepository::TYPE_DEFAULT, 'keyword' => 'ignored']);
        $id = $this->createRule([
            'type' => WechatAutoReplyRepository::TYPE_DEFAULT,
            'keyword' => 'ignored',
            'status' => WechatAutoReplyRepository::STATUS_DISABLED,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(lang('wechat.reply_exists_default'));
        $this->service()->update($id, ['status' => 1]);
    }

    public function test_create_forces_text_and_normalizes_defaults(): void
    {
        $this->service()->create([
            'type' => WechatAutoReplyRepository::TYPE_SUBSCRIBE,
            'keyword' => 'ignored',
            'match_type' => WechatAutoReplyRepository::MATCH_FUZZY,
            'reply_type' => 'news',
            'content' => 'welcome',
        ]);

        $row = Db::table('wechat_auto_replies')->first();
        $this->assertNotNull($row);
        $this->assertSame('text', $row->reply_type);
        $this->assertSame('', $row->keyword);
        $this->assertSame('exact', $row->match_type);
        $this->assertSame(1, (int) $row->status);
    }

    public function test_get_detail_rejects_missing_rule(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage(lang('wechat.auto_reply_not_found'));
        $this->service()->getDetail(999999999);
    }

    public function test_delete_removes_rule_from_matching(): void
    {
        $id = $this->createRule(['content' => 'found']);
        $this->assertSame('found', $this->service()->matchKeyword('hello'));

        $this->service()->delete($id);

        $this->assertNull($this->service()->matchKeyword('hello'));
    }
}
