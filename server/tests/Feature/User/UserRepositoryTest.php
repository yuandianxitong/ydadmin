<?php

declare(strict_types=1);

namespace tests\Feature\User;

use app\repository\user\UserRepository;
use support\Db;
use tests\TestCase;

final class UserRepositoryTest extends TestCase
{
    /** @var list<int> */
    private array $userIds = [];

    protected function tearDown(): void
    {
        if ($this->userIds !== []) {
            Db::table('users')->whereIn('id', $this->userIds)->delete();
        }
        $this->userIds = [];
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function user(array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $mobile = '1' . str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        $id = (int) Db::table('users')->insertGetId(array_merge([
            'nickname'    => '会员' . bin2hex(random_bytes(3)),
            'mobile'      => $mobile,
            'password'    => password_hash('secret123', PASSWORD_BCRYPT),
            'gender'      => 0,
            'status'      => 1,
            'balance'     => '0.00',
            'points'      => 0,
            'login_count' => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ], $attributes));
        $this->userIds[] = $id;

        return $id;
    }

    public function test_find_by_account_matches_by_mobile_and_ignores_soft_deleted(): void
    {
        $id = $this->user(['mobile' => '13911112222']);
        $deletedId = $this->user(['mobile' => '13922223333', 'deleted_at' => date('Y-m-d H:i:s')]);
        $repo = new UserRepository();

        $found = $repo->findByAccount('13911112222');
        $this->assertNotNull($found);
        $this->assertSame($id, (int) $found['id']);

        $this->assertNull($repo->findByAccount('13922223333'), '软删会员不可被找到');
        $this->assertNull($repo->findByAccount('13900000000'));
        unset($deletedId);
    }

    public function test_find_for_update_returns_row_by_id(): void
    {
        $id = $this->user(['balance' => '66.60']);

        $row = (new UserRepository())->findForUpdate($id);

        $this->assertNotNull($row);
        $this->assertSame($id, (int) $row['id']);
        $this->assertSame('66.60', $row['balance']);
        $this->assertNull((new UserRepository())->findForUpdate(0));
    }

    public function test_update_last_login_increments_count_and_sets_ip_and_time(): void
    {
        $id = $this->user(['login_count' => 2]);

        $ok = (new UserRepository())->updateLastLogin($id, '10.0.0.1');

        $this->assertTrue($ok);
        $row = Db::table('users')->where('id', $id)->first();
        $this->assertSame(3, (int) $row->login_count);
        $this->assertSame('10.0.0.1', $row->last_login_ip);
        $this->assertNotNull($row->last_login_time);
    }

    public function test_mobile_exists_true_only_for_existing_mobile(): void
    {
        $this->user(['mobile' => '13733334444']);
        $repo = new UserRepository();

        $this->assertTrue($repo->mobileExists('13733334444'));
        $this->assertFalse($repo->mobileExists('13700000000'));
    }

    public function test_get_manage_list_filters_by_keyword_across_nickname_and_mobile(): void
    {
        $tag = bin2hex(random_bytes(3));
        $byNickname = $this->user(['nickname' => "客户{$tag}"]);
        $byMobile = $this->user(['mobile' => '138' . substr($tag, 0, 8)]);
        $this->user(['nickname' => '无关会员']);
        $repo = new UserRepository();

        $result = $repo->getManageList(['keyword' => $tag], 1, 20);

        $ids = array_map('intval', array_column($result['list'], 'id'));
        $this->assertContains($byNickname, $ids);
        $this->assertContains($byMobile, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_get_manage_list_filters_by_status_and_orders_newest_first(): void
    {
        $tag = bin2hex(random_bytes(3));
        $disabled = $this->user(['nickname' => "状态{$tag}A", 'status' => 0]);
        $enabledOld = $this->user(['nickname' => "状态{$tag}B", 'status' => 1]);
        $enabledNew = $this->user(['nickname' => "状态{$tag}C", 'status' => 1]);
        $repo = new UserRepository();

        $enabled = $repo->getManageList(['keyword' => $tag, 'status' => 1], 1, 20);
        $this->assertSame([$enabledNew, $enabledOld], array_map('intval', array_column($enabled['list'], 'id')));

        $disabledOnly = $repo->getManageList(['keyword' => $tag, 'status' => 0], 1, 20);
        $this->assertSame([$disabled], array_map('intval', array_column($disabledOnly['list'], 'id')));
    }

    public function test_count_all_and_count_created_between(): void
    {
        $before = (new UserRepository())->countAll();
        $this->user();
        $this->user();

        $this->assertSame($before + 2, (new UserRepository())->countAll());

        $tag = bin2hex(random_bytes(3));
        $inRange = $this->user(['nickname' => "范围{$tag}", 'created_at' => '2026-01-10 12:00:00']);
        $outOfRange = $this->user(['nickname' => "范围{$tag}外", 'created_at' => '2026-02-01 00:00:00']);
        $repo = new UserRepository();

        $count = $repo->countCreatedBetween('2026-01-01 00:00:00', '2026-01-31 23:59:59');
        $this->assertGreaterThanOrEqual(1, $count);
        $row = Db::table('users')->whereIn('id', [$inRange, $outOfRange])
            ->whereBetween('created_at', ['2026-01-01 00:00:00', '2026-01-31 23:59:59'])->pluck('id')->all();
        $this->assertSame([$inRange], array_map('intval', $row));
    }

    public function test_count_active_since_only_counts_recent_logins(): void
    {
        $tag = bin2hex(random_bytes(3));
        $recent = $this->user(['nickname' => "活跃{$tag}", 'last_login_time' => date('Y-m-d H:i:s')]);
        $stale = $this->user(['nickname' => "活跃{$tag}旧", 'last_login_time' => '2020-01-01 00:00:00']);
        $never = $this->user(['nickname' => "活跃{$tag}未"]);
        $repo = new UserRepository();

        $since = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $ids = array_map('intval', Db::table('users')->whereIn('id', [$recent, $stale, $never])
            ->where('last_login_time', '>=', $since)->pluck('id')->all());
        $this->assertSame([$recent], $ids);
        $this->assertGreaterThanOrEqual(1, $repo->countActiveSince($since));
    }

    public function test_register_trend_fills_zero_for_days_without_registrations(): void
    {
        $tag = bin2hex(random_bytes(3));
        $today = date('Y-m-d');
        $this->user(['nickname' => "趋势{$tag}", 'created_at' => $today . ' 08:00:00']);
        $this->user(['nickname' => "趋势{$tag}2", 'created_at' => $today . ' 09:00:00']);

        $trend = (new UserRepository())->registerTrend(3);

        $this->assertCount(3, $trend);
        // 行里的 date 是 m-d（与 loginTrend 一致）；created_at 夹具仍是 Y-m-d H:i:s，两者不要混
        $this->assertSame(date('m-d'), $trend[2]['date']);
        $this->assertGreaterThanOrEqual(2, $trend[2]['count']);
        $this->assertSame(
            [$trend[0]['date'], $trend[1]['date'], $trend[2]['date']],
            [
                date('m-d', strtotime('-2 days')),
                date('m-d', strtotime('-1 day')),
                date('m-d'),
            ]
        );
    }
}
