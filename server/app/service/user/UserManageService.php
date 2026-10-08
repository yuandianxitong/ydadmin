<?php

declare(strict_types=1);

namespace app\service\user;

use app\repository\system\AdminRepository;
use app\repository\user\BalanceLogRepository;
use app\repository\user\PointsLogRepository;
use app\repository\user\UserRepository;
use core\auth\TokenVersion;
use core\base\Service;
use core\datascope\DataScope;
use core\exception\NotFoundException;
use DI\Attribute\Inject;

/**
 * 管理端会员管理（spec §6.3）。列表与两个流水列表的字段、查询参数逐字对齐 admin 前端
 * （admin/src/api/user.ts、admin/src/types/user.d.ts、views/user/*），前端不改。
 *
 * - 调余额、调积分一律经 BalanceService / PointsService 的唯一写入口（spec §5.1），
 *   source 固定 admin_adjust、type 固定「后台调整」、operator_id 是当次的管理员。
 *   结果为负由 change() 抛 422（errors.amount / errors.points），这里不重复判。
 * - 状态置 0 时自增 user scope 的 token 版本号，被禁用的会员下一次请求即 401（spec §4.2）。
 * - users / balance_logs / points_logs 三张表不受数据权限约束（spec §8，红线 Test22 钉住），
 *   管理端按 TP8 行为全表可见。
 */
class UserManageService extends Service
{
    /** 流水的来源标识：管理端调整。M5b 充值将用 recharge。 */
    public const SOURCE_ADMIN_ADJUST = 'admin_adjust';

    #[Inject]
    protected UserRepository $userRepository;

    #[Inject]
    protected BalanceLogRepository $balanceLogRepository;

    #[Inject]
    protected PointsLogRepository $pointsLogRepository;

    #[Inject]
    protected AdminRepository $adminRepository;

    #[Inject]
    protected BalanceService $balanceService;

    #[Inject]
    protected PointsService $pointsService;

    /**
     * 会员列表。$params：keyword（昵称 / 手机号）、status（0 或 1）。
     *
     * @param array<string, mixed> $params
     * @return array{list: list<array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getList(array $params, int $page, int $limit): array
    {
        $result = $this->userRepository->getManageList($params, $page, $limit);
        // 按 UserItem 的 11 个字段整形：顺带保证 password 之类的列不可能出现在列表里（红线 Test20）
        $result['list'] = array_map(static fn (array $row): array => [
            'id'              => (int) $row['id'],
            'nickname'        => (string) ($row['nickname'] ?? ''),
            'avatar'          => (string) ($row['avatar'] ?? ''),
            'mobile'          => (string) ($row['mobile'] ?? ''),
            'balance'         => (string) ($row['balance'] ?? '0.00'),
            'points'          => (int) ($row['points'] ?? 0),
            'status'          => (int) ($row['status'] ?? 0),
            'last_login_ip'   => (string) ($row['last_login_ip'] ?? ''),
            'last_login_time' => (string) ($row['last_login_time'] ?? ''),
            'login_count'     => (int) ($row['login_count'] ?? 0),
            'created_at'      => (string) ($row['created_at'] ?? ''),
        ], $result['list']);

        return $result;
    }

    /**
     * 会员详情：保留微信四列（运营要看绑定情况），不含 password——模型 $hidden 已经挡了一道，
     * 这里再 unset 一次是双保险（红线 Test20 钉的是出口，不是实现）。
     *
     * @return array<string, mixed>
     */
    public function getDetail(int $id): array
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            throw new NotFoundException();
        }
        unset($user['password']);

        return $user;
    }

    /** 后台调整余额：负数即调减，结果为负由 change() 抛 422（errors.amount）。 */
    public function adjustBalance(int $userId, float $amount, string $remark, int $operatorId): void
    {
        $this->balanceService->change($userId, $amount, BalanceLogRepository::TYPE_ADMIN_ADJUST, self::SOURCE_ADMIN_ADJUST, $remark, $operatorId);
    }

    /** 后台调整积分：负数即调减，结果为负由 change() 抛 422（errors.points）。 */
    public function adjustPoints(int $userId, int $points, string $remark, int $operatorId): void
    {
        $this->pointsService->change($userId, $points, PointsLogRepository::TYPE_ADMIN_ADJUST, self::SOURCE_ADMIN_ADJUST, $remark, $operatorId);
    }

    /** 启用 / 禁用。置 0 时在同一个事务里自增 user scope 的 token 版本号，该会员已签发的 token 全部失效。 */
    public function updateStatus(int $id, int $status): bool
    {
        if ($this->userRepository->find($id) === null) {
            throw new NotFoundException();
        }

        return $this->runInTransaction(function () use ($id, $status): bool {
            $updated = $this->userRepository->update($id, ['status' => $status]);
            if ($status === 0) {
                TokenVersion::bump($id, 'user');
            }

            return $updated;
        });
    }

    /**
     * 余额流水。$params：keyword（昵称 / 手机号）、type、start_date、end_date。
     *
     * @param array<string, mixed> $params
     * @return array{list: list<array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getBalanceLogs(array $params, int $page, int $limit): array
    {
        return $this->withOperatorNames($this->balanceLogRepository->getManageList($params, $page, $limit));
    }

    /**
     * 积分流水，参数同上。
     *
     * @param array<string, mixed> $params
     * @return array{list: list<array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getPointsLogs(array $params, int $page, int $limit): array
    {
        return $this->withOperatorNames($this->pointsLogRepository->getManageList($params, $page, $limit));
    }

    /**
     * 补上 operator_name（admin 两个流水页的「操作人」列）。
     *
     * 经 DataScope::bypass()：balance_logs / points_logs / users 三表按 spec §8 不受数据权限约束，
     * 而 AdminRepository 是受控表——不 bypass 的话，范围外管理员的名字查不到，同一条流水在不同
     * 查看者眼里一会儿有名字一会儿是「-」。这里只读用户名做展示，不做任何访问控制判定。
     *
     * @param array{list: list<array<string, mixed>>, pagination: array<string, int>} $result
     * @return array{list: list<array<string, mixed>>, pagination: array<string, int>}
     */
    private function withOperatorNames(array $result): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (array $row): int => (int) ($row['operator_id'] ?? 0), $result['list'])
        )));
        /** @var array<int, array{id: int, username: string, nickname: string}> $admins */
        $admins = $ids === [] ? [] : DataScope::bypass(fn (): array => $this->adminRepository->briefByIds($ids));

        foreach ($result['list'] as $index => $row) {
            $operatorId = (int) ($row['operator_id'] ?? 0);
            $result['list'][$index]['operator_id'] = $operatorId > 0 ? $operatorId : null;
            $result['list'][$index]['operator_name'] = isset($admins[$operatorId])
                ? ($admins[$operatorId]['nickname'] !== '' ? $admins[$operatorId]['nickname'] : $admins[$operatorId]['username'])
                : null;
        }

        return $result;
    }
}
