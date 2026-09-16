<?php

declare(strict_types=1);

namespace app\service\user;

use app\repository\user\PointsLogRepository;
use app\repository\user\UserRepository;
use core\base\Service;
use core\exception\NotFoundException;
use core\exception\ValidationException;
use DI\Attribute\Inject;

/**
 * 积分变动的唯一写入口（spec §5.1），与 BalanceService 同构：runInTransaction 内先 findForUpdate()
 * 行锁读当前值，校验结果不为负，再更新用户表并写流水，before_points / after_points 取锁内读到的值。
 *
 * 同样不用 Repository::inc()/dec()：拿不到前后值，也挡不住扣成负数（points 是有符号 int 列）。
 * 积分是整数，不需要余额那套「分」的换算。
 */
class PointsService extends Service
{
    #[Inject]
    protected UserRepository $userRepository;

    #[Inject]
    protected PointsLogRepository $pointsLogRepository;

    /**
     * @param int      $points     正数增加、负数扣减；结果为负抛 422（errors.points）
     * @param int      $type       PointsLog::TYPE_*（1 后台调整 2 注册赠送 3 签到 4 消费赠送 5 消费扣减）
     * @param string   $source     来源标识：管理端调整 admin_adjust
     * @param int|null $operatorId 操作管理员 id；用户自己触发的变动为 null
     * @return array{before: int, after: int}
     */
    public function change(int $userId, int $points, int $type, string $source, string $remark = '', ?int $operatorId = null): array
    {
        return $this->runInTransaction(function () use ($userId, $points, $type, $source, $remark, $operatorId): array {
            $user = $this->userRepository->findForUpdate($userId);
            if ($user === null) {
                throw new NotFoundException();
            }

            $before = (int) ($user['points'] ?? 0);
            $after = $before + $points;
            if ($after < 0) {
                throw new ValidationException(['points' => lang('validation.points_not_enough')]);
            }

            $this->userRepository->update($userId, ['points' => $after]);
            $this->pointsLogRepository->create([
                'user_id'       => $userId,
                'points'        => $points,
                'before_points' => $before,
                'after_points'  => $after,
                'type'          => $type,
                'source'        => $source,
                'remark'        => $remark,
                'operator_id'   => $operatorId,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);

            return ['before' => $before, 'after' => $after];
        });
    }
}
