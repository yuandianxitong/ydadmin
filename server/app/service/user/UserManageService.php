<?php
declare(strict_types=1);

namespace app\service\user;

use app\model\user\BalanceLog;
use app\model\user\PointsLog;
use app\repository\user\BalanceLogRepository;
use app\repository\user\PointsLogRepository;
use app\repository\user\UserRepository;
use core\base\Service;
use think\facade\Db;

class UserManageService extends Service
{
    protected UserRepository $userRepository;
    protected BalanceLogRepository $balanceLogRepository;
    protected PointsLogRepository $pointsLogRepository;

    public function getUserList(array $params): array
    {
        [$page, $limit] = $this->extractPagination($params);
        return $this->userRepository->getSearchList($params, $page, $limit);
    }

    public function getUserDetail(int $id): ?array
    {
        $user = $this->userRepository->find($id);
        return $user ?: null;
    }

    public function updateStatus(int $id, int $status): bool
    {
        return $this->userRepository->update($id, ['status' => $status]);
    }

    public function adjustBalance(int $userId, float $amount, string $remark = '', int $type = BalanceLog::TYPE_ADMIN_ADJUST, string $source = 'admin_adjust', ?int $operatorId = null): bool
    {
        // 幂等性检查
        if ($source && $source !== 'admin_adjust' && $this->balanceLogRepository->existsBySource($source, $userId)) {
            return true;
        }

        return $this->runInTransaction(function () use ($userId, $amount, $type, $source, $remark, $operatorId) {
            $user = $this->userRepository->findForUpdate($userId);
            if (!$user) {
                $this->throwBusinessException('用户不存在');
            }

            $beforeBalance = (float) $user->balance;
            $afterBalance  = $beforeBalance + $amount;

            if ($afterBalance < 0) {
                $this->throwBusinessException('余额不足');
            }

            $user->balance = $afterBalance;
            $user->save();

            $this->balanceLogRepository->create([
                'user_id'        => $userId,
                'amount'         => $amount,
                'before_balance' => $beforeBalance,
                'after_balance'  => $afterBalance,
                'type'           => $type,
                'source'         => $source,
                'remark'         => $remark,
                'operator_id'    => $operatorId,
            ]);

            return true;
        });
    }

    public function adjustPoints(int $userId, int $points, string $remark = '', int $type = PointsLog::TYPE_ADMIN_ADJUST, string $source = 'admin_adjust', ?int $operatorId = null): bool
    {
        return $this->runInTransaction(function () use ($userId, $points, $type, $source, $remark, $operatorId) {
            $user = $this->userRepository->findForUpdate($userId);
            if (!$user) {
                $this->throwBusinessException('用户不存在');
            }

            $beforePoints = (int) $user->points;
            $afterPoints  = $beforePoints + $points;

            if ($afterPoints < 0) {
                $this->throwBusinessException('积分不足');
            }

            $user->points = $afterPoints;
            $user->save();

            $this->pointsLogRepository->create([
                'user_id'       => $userId,
                'points'        => $points,
                'before_points' => $beforePoints,
                'after_points'  => $afterPoints,
                'type'          => $type,
                'source'        => $source,
                'remark'        => $remark,
                'operator_id'   => $operatorId,
            ]);

            return true;
        });
    }

    public function getBalanceLogs(array $params): array
    {
        [$page, $limit] = $this->extractPagination($params);

        if (!empty($params['keyword'])) {
            $params['user_ids'] = $this->userRepository->searchIdsByKeyword($params['keyword']) ?: [0];
        }

        return $this->balanceLogRepository->getSearchList($params, $page, $limit);
    }

    public function getPointsLogs(array $params): array
    {
        [$page, $limit] = $this->extractPagination($params);

        if (!empty($params['keyword'])) {
            $params['user_ids'] = $this->userRepository->searchIdsByKeyword($params['keyword']) ?: [0];
        }

        return $this->pointsLogRepository->getSearchList($params, $page, $limit);
    }

    public function getUserBalance(int $userId): array
    {
        $user = $this->userRepository->find($userId);
        return ['balance' => $user['balance'] ?? '0.00'];
    }

    public function getUserPoints(int $userId): array
    {
        $user = $this->userRepository->find($userId);
        return ['points' => $user['points'] ?? 0];
    }

    public function getUserBalanceLogs(int $userId, array $params): array
    {
        [$page, $limit] = $this->extractPagination($params, 10);
        return $this->balanceLogRepository->getUserLogs($userId, $page, $limit);
    }

    public function getUserPointsLogs(int $userId, array $params): array
    {
        [$page, $limit] = $this->extractPagination($params, 10);
        return $this->pointsLogRepository->getUserLogs($userId, $page, $limit);
    }
}
