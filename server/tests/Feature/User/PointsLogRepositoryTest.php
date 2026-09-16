<?php

declare(strict_types=1);

namespace tests\Feature\User;

use app\repository\user\PointsLogRepository;
use support\Db;
use tests\TestCase;

final class PointsLogRepositoryTest extends TestCase
{
    /** @var list<int> */
    private array $userIds = [];

    /** @var list<int> */
    private array $logIds = [];

    protected function tearDown(): void
    {
        if ($this->logIds !== []) {
            Db::table('points_logs')->whereIn('id', $this->logIds)->delete();
        }
        if ($this->userIds !== []) {
            Db::table('users')->whereIn('id', $this->userIds)->delete();
        }
        $this->logIds = [];
        $this->userIds = [];
        parent::tearDown();
    }

    private function user(string $nickname, string $mobile): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('users')->insertGetId([
            'nickname' => $nickname, 'mobile' => $mobile, 'status' => 1,
            'balance' => '0.00', 'points' => 0, 'login_count' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->userIds[] = $id;

        return $id;
    }

    /** @param array<string, mixed> $attributes */
    private function log(int $userId, array $attributes = []): int
    {
        $id = (int) Db::table('points_logs')->insertGetId(array_merge([
            'user_id'       => $userId,
            'points'        => 10,
            'before_points' => 0,
            'after_points'  => 10,
            'type'          => 1,
            'source'        => 'admin_adjust',
            'remark'        => '',
            'created_at'    => date('Y-m-d H:i:s'),
        ], $attributes));
        $this->logIds[] = $id;

        return $id;
    }

    public function test_get_user_list_only_returns_that_user_newest_first_with_pagination(): void
    {
        $mine = $this->user('甲', '13500000001');
        $other = $this->user('乙', '13500000002');
        $first = $this->log($mine, ['created_at' => '2026-01-01 00:00:00']);
        $second = $this->log($mine, ['created_at' => '2026-01-02 00:00:00']);
        $this->log($other);

        $result = (new PointsLogRepository())->getUserList($mine, 1, 1);

        $this->assertSame([$second], array_map('intval', array_column($result['list'], 'id')));
        $this->assertSame(['current_page' => 1, 'per_page' => 1, 'total' => 2, 'last_page' => 2], $result['pagination']);
        $page2 = (new PointsLogRepository())->getUserList($mine, 2, 1);
        $this->assertSame([$first], array_map('intval', array_column($page2['list'], 'id')));
    }

    public function test_get_manage_list_joins_user_nickname(): void
    {
        $user = $this->user('张三', '13500000003');
        $logId = $this->log($user, ['type' => 3]);

        $result = (new PointsLogRepository())->getManageList([], 1, 100);

        $row = current(array_filter($result['list'], static fn (array $r): bool => (int) $r['id'] === $logId));
        $this->assertNotFalse($row);
        $this->assertSame('张三', $row['user_nickname']);
        $this->assertSame('签到', $row['type_text']);
    }

    public function test_get_manage_list_filters_by_type(): void
    {
        $user = $this->user('李四', '13500000004');
        $adminAdjust = $this->log($user, ['type' => 1]);
        $signIn = $this->log($user, ['type' => 3]);

        $result = (new PointsLogRepository())->getManageList(['type' => 3], 1, 100);

        $ids = array_map('intval', array_column($result['list'], 'id'));
        $this->assertContains($signIn, $ids);
        $this->assertNotContains($adminAdjust, $ids);
    }

    public function test_get_manage_list_filters_by_date_range(): void
    {
        $user = $this->user('王五', '13500000005');
        $inRange = $this->log($user, ['created_at' => '2026-03-15 10:00:00']);
        $outOfRange = $this->log($user, ['created_at' => '2026-04-01 10:00:00']);

        $result = (new PointsLogRepository())->getManageList([
            'start_date' => '2026-03-01', 'end_date' => '2026-03-31',
        ], 1, 100);

        $ids = array_map('intval', array_column($result['list'], 'id'));
        $this->assertContains($inRange, $ids);
        $this->assertNotContains($outOfRange, $ids);
    }

    public function test_get_manage_list_filters_by_keyword_across_nickname_and_mobile(): void
    {
        $tag = bin2hex(random_bytes(3));
        $matched = $this->user("积分{$tag}", '13500000006');
        $unmatched = $this->user('无关会员', '13500000007');
        $matchedLog = $this->log($matched);
        $unmatchedLog = $this->log($unmatched);

        $result = (new PointsLogRepository())->getManageList(['keyword' => $tag], 1, 100);

        $ids = array_map('intval', array_column($result['list'], 'id'));
        $this->assertSame([$matchedLog], $ids);
        unset($unmatchedLog);
    }
}
