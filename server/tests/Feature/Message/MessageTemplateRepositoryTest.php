<?php

declare(strict_types=1);

namespace tests\Feature\Message;

use app\repository\message\MessageTemplateRepository;
use support\Db;
use tests\TestCase;

/**
 * spec §2.1 / §4.1：按编码取启用模板（停用、软删都取不到）；编码查重含软删行；管理端列表 keyword/status 过滤；
 * update() 覆盖基类后 JSON 列按 array cast 落库。
 */
final class MessageTemplateRepositoryTest extends TestCase
{
    /** @var list<int> */
    private array $ids = [];

    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = 'rt' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        try {
            if ($this->ids !== []) {
                Db::table('message_templates')->whereIn('id', $this->ids)->delete();
            }
        } finally {
            $this->ids = [];
            parent::tearDown();
        }
    }

    /** @param array<string, mixed> $attributes */
    private function template(string $suffix, array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('message_templates')->insertGetId(array_merge([
            'name'       => '模板' . $suffix,
            'code'       => $this->prefix . '_' . $suffix,
            'status'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
        $this->ids[] = $id;

        return $id;
    }

    public function test_builtin_codes_and_status_constant(): void
    {
        $this->assertSame(['user_register', 'payment_success', 'feedback_received'], MessageTemplateRepository::BUILTIN_CODES);
        $this->assertSame(1, MessageTemplateRepository::STATUS_ENABLED);
    }

    public function test_find_active_by_code_skips_disabled_and_soft_deleted(): void
    {
        $id = $this->template('active', [
            'wechat_mini_data' => json_encode(['thing1' => '${amount}']),
            'variables'        => json_encode([['key' => 'amount', 'name' => '金额', 'example' => '1.00']]),
        ]);
        $this->template('disabled', ['status' => 0]);
        $this->template('trashed', ['deleted_at' => date('Y-m-d H:i:s')]);
        $repo = new MessageTemplateRepository();

        $found = $repo->findActiveByCode($this->prefix . '_active');
        $this->assertNotNull($found);
        $this->assertSame($id, (int) $found['id']);
        $this->assertSame(['thing1' => '${amount}'], $found['wechat_mini_data'], 'JSON 列按 array cast 取回');
        $this->assertSame([['key' => 'amount', 'name' => '金额', 'example' => '1.00']], $found['variables']);
        $this->assertNull($found['wechat_official_data']);

        $this->assertNull($repo->findActiveByCode($this->prefix . '_disabled'));
        $this->assertNull($repo->findActiveByCode($this->prefix . '_trashed'));
        $this->assertNull($repo->findActiveByCode($this->prefix . '_missing'));
    }

    public function test_code_exists_includes_soft_deleted_rows(): void
    {
        $this->template('live');
        $this->template('gone', ['deleted_at' => date('Y-m-d H:i:s')]);
        $repo = new MessageTemplateRepository();

        $this->assertTrue($repo->codeExists($this->prefix . '_live'));
        $this->assertTrue($repo->codeExists($this->prefix . '_gone'), '软删行仍占用编码（uk_code 不区分软删）');
        $this->assertFalse($repo->codeExists($this->prefix . '_none'));
    }

    public function test_admin_list_filters_by_keyword_and_status_newest_first(): void
    {
        $a = $this->template('alpha', ['name' => '注册成功' . $this->prefix]);
        $b = $this->template('beta', ['status' => 0]);
        $this->template('trashed', ['deleted_at' => date('Y-m-d H:i:s')]);
        $repo = new MessageTemplateRepository();

        $all = $repo->getAdminList(['keyword' => $this->prefix], 1, 10);
        $this->assertSame([$b, $a], array_map('intval', array_column($all['list'], 'id')), '按编码命中、id 倒序、不含软删');
        $this->assertSame(['current_page' => 1, 'per_page' => 10, 'total' => 2, 'last_page' => 1], $all['pagination']);

        $byName = $repo->getAdminList(['keyword' => '注册成功' . $this->prefix], 1, 10);
        $this->assertSame([$a], array_map('intval', array_column($byName['list'], 'id')), '按名称命中');

        $disabled = $repo->getAdminList(['keyword' => $this->prefix, 'status' => '0'], 1, 10);
        $this->assertSame([$b], array_map('intval', array_column($disabled['list'], 'id')));

        $blankStatus = $repo->getAdminList(['keyword' => $this->prefix, 'status' => ''], 1, 10);
        $this->assertSame(2, $blankStatus['pagination']['total'], 'status 为空串不过滤');

        $wildcard = $repo->getAdminList(['keyword' => $this->prefix . '%'], 1, 10);
        $this->assertSame(0, $wildcard['pagination']['total'], '% 按字面匹配');
    }

    public function test_update_casts_json_columns(): void
    {
        $id = $this->template('upd');
        $repo = new MessageTemplateRepository();

        $this->assertTrue($repo->update($id, [
            'wechat_official_data' => ['character_string1' => '${order_no}'],
            'variables'            => [['key' => 'order_no', 'name' => '订单号', 'example' => 'P1']],
            'site_title'           => '支付成功',
        ]));

        $row = $repo->find($id);
        $this->assertNotNull($row);
        $this->assertSame(['character_string1' => '${order_no}'], $row['wechat_official_data']);
        $this->assertSame('支付成功', $row['site_title']);
        $this->assertFalse($repo->update(999999999, ['site_title' => 'x']), '不存在返回 false');
    }
}
