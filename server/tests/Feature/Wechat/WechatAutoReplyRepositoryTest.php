<?php

declare(strict_types=1);

namespace tests\Feature\Wechat;

use app\repository\wechat\WechatAutoReplyRepository;
use support\Db;
use tests\TestCase;

final class WechatAutoReplyRepositoryTest extends TestCase
{
    private WechatAutoReplyRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new WechatAutoReplyRepository();
    }

    protected function tearDown(): void
    {
        Db::table('wechat_auto_replies')->delete();
        parent::tearDown();
    }

    public function test_create_forces_text_reply_type_and_does_not_write_created_by(): void
    {
        $row = $this->repo->create([
            'type'       => WechatAutoReplyRepository::TYPE_KEYWORD,
            'keyword'    => 'hello',
            'match_type' => WechatAutoReplyRepository::MATCH_EXACT,
            'reply_type' => 'news',
            'content'    => 'hi',
            'status'     => WechatAutoReplyRepository::STATUS_ENABLED,
            'sort_order' => 0,
        ]);
        $this->assertSame('text', $row['reply_type']);
        $this->assertArrayNotHasKey('created_by', $row);

        $this->assertTrue($this->repo->update((int) $row['id'], ['reply_type' => 'news', 'content' => 'updated']));
        $updated = $this->repo->findById((int) $row['id']);
        $this->assertSame('text', $updated['reply_type'] ?? null);
        $this->assertSame('updated', $updated['content'] ?? null);
    }

    public function test_exact_match_ignores_disabled_and_soft_deleted(): void
    {
        $winner = $this->repo->create($this->kw('hello', 'A', 1, 0));
        $this->repo->create($this->kw('hello', 'same-sort-loser', 1, 0));
        $disabled = $this->repo->create($this->kw('hello', 'B', 1, 0, WechatAutoReplyRepository::STATUS_DISABLED));
        $deleted = $this->repo->create($this->kw('hello', 'C', 0, 0));
        $this->repo->delete((int) $deleted['id']);

        $hit = $this->repo->findExactKeyword('hello');
        $this->assertSame('A', $hit['content'] ?? null);
        $this->assertSame($winner['id'], $hit['id'] ?? null);
        $this->assertNotSame($disabled['id'], $hit['id'] ?? null);
    }

    public function test_fuzzy_rules_skip_empty_keyword_and_order_by_sort_then_id(): void
    {
        $this->repo->create($this->kw('ab', 'second', 2, 1));
        $this->repo->create($this->kw('a', 'first', 1, 1));
        $this->repo->create($this->kw('', 'empty', 0, 1));
        $this->repo->create($this->kw('late', 'tie-late', 5, 1));
        $this->repo->create($this->kw('early', 'tie-early', 5, 1));
        $rules = $this->repo->fuzzyRules();
        $this->assertSame(['first', 'second', 'tie-late', 'tie-early'], array_column($rules, 'content'));
    }

    public function test_exists_enabled_by_type_respects_except_id(): void
    {
        $row = $this->repo->create([
            'type' => WechatAutoReplyRepository::TYPE_SUBSCRIBE, 'keyword' => '',
            'match_type' => WechatAutoReplyRepository::MATCH_EXACT,
            'reply_type' => WechatAutoReplyRepository::REPLY_TEXT,
            'content' => '欢迎', 'status' => 1, 'sort_order' => 0,
        ]);
        $this->assertTrue($this->repo->existsEnabledByType('subscribe'));
        $this->assertFalse($this->repo->existsEnabledByType('subscribe', (int) $row['id']));
    }

    /** @return array<string, mixed> */
    private function kw(string $keyword, string $content, int $sort, int $fuzzy, int $status = 1): array
    {
        return [
            'type' => WechatAutoReplyRepository::TYPE_KEYWORD,
            'keyword' => $keyword,
            'match_type' => $fuzzy === 1 ? WechatAutoReplyRepository::MATCH_FUZZY : WechatAutoReplyRepository::MATCH_EXACT,
            'reply_type' => WechatAutoReplyRepository::REPLY_TEXT,
            'content' => $content,
            'status' => $status,
            'sort_order' => $sort,
        ];
    }
}
