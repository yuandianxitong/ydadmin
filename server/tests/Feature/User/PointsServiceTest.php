<?php

declare(strict_types=1);

namespace tests\Feature\User;

use app\model\user\PointsLog;
use app\service\user\PointsService;
use core\exception\NotFoundException;
use core\exception\ValidationException;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;

/** spec §5.1：积分变动的唯一写入口，与余额同构；结果为负一律 422（errors.points）。 */
final class PointsServiceTest extends ApiTestCase
{
    /** @var list<int> */
    private array $userIds = [];

    protected function tearDown(): void
    {
        if ($this->userIds !== []) {
            Db::table('points_logs')->whereIn('user_id', $this->userIds)->delete();
        }
        $this->userIds = [];
        parent::tearDown();
    }

    private function createUser(int $points = 100): int
    {
        $now = date('Y-m-d H:i:s');
        $suffix = bin2hex(random_bytes(4));
        $id = (int) Db::table('users')->insertGetId([
            'nickname'   => "u_{$suffix}",
            'mobile'     => '19' . str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT),
            'password'   => password_hash('Passw0rd!', PASSWORD_DEFAULT),
            'status'     => 1,
            'balance'    => '0.00',
            'points'     => $points,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->track('users', $id);
        $this->userIds[] = $id;

        return $id;
    }

    private function service(): PointsService
    {
        return Container::get(PointsService::class);
    }

    public function test_change_updates_the_points_and_writes_one_ledger_row(): void
    {
        $userId = $this->createUser(100);

        $result = $this->service()->change($userId, 30, PointsLog::TYPE_REGISTER, 'register', '注册赠送');

        $this->assertSame(['before' => 100, 'after' => 130], $result);
        $this->assertSame(130, (int) Db::table('users')->where('id', $userId)->value('points'));

        $log = Db::table('points_logs')->where('user_id', $userId)->first();
        $this->assertSame(30, (int) $log->points);
        $this->assertSame(100, (int) $log->before_points);
        $this->assertSame(130, (int) $log->after_points);
        $this->assertSame(PointsLog::TYPE_REGISTER, (int) $log->type);
        $this->assertSame('register', (string) $log->source);
        $this->assertSame('注册赠送', (string) $log->remark);
        $this->assertNull($log->operator_id);
    }

    public function test_deduction_records_the_operator(): void
    {
        $userId = $this->createUser(50);

        $this->assertSame(['before' => 50, 'after' => 20], $this->service()->change($userId, -30, PointsLog::TYPE_ADMIN_ADJUST, 'admin_adjust', '后台调减', 7));
        $this->assertSame(7, (int) Db::table('points_logs')->where('user_id', $userId)->value('operator_id'));
    }

    public function test_a_negative_result_is_rejected_and_nothing_is_written(): void
    {
        $userId = $this->createUser(10);

        try {
            $this->service()->change($userId, -11, PointsLog::TYPE_CONSUME_DEDUCT, 'consume');
            $this->fail('扣成负数必须抛 ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertSame(['points' => lang('validation.points_not_enough')], $e->errors());
        }

        $this->assertSame(10, (int) Db::table('users')->where('id', $userId)->value('points'));
        $this->assertSame(0, Db::table('points_logs')->where('user_id', $userId)->count());
    }

    public function test_unknown_user_is_404(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service()->change(999_999_999, 1, PointsLog::TYPE_ADMIN_ADJUST, 'admin_adjust');
    }
}
