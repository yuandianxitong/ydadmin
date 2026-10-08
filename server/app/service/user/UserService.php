<?php

declare(strict_types=1);

namespace app\service\user;

use app\repository\user\BalanceLogRepository;
use app\repository\user\PointsLogRepository;
use app\repository\user\UserRepository;
use core\auth\TokenVersion;
use core\base\Service;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use DI\Attribute\Inject;

/**
 * C 端会员自助（spec §6.2）：资料读改、改密、余额与积分（含流水）。users/balance_logs/points_logs
 * 都不受数据权限约束（spec §8），安全性靠「查询一律带 user_id 条件」——两个流水列表都转发到
 * XxxLogRepository::getUserList(int $userId, ...)，不接受调用方传别的 user_id。
 */
class UserService extends Service
{
    #[Inject]
    protected UserRepository $userRepository;

    #[Inject]
    protected BalanceLogRepository $balanceLogRepository;

    #[Inject]
    protected PointsLogRepository $pointsLogRepository;

    /** @return array<string, mixed> 收窄后的整行（无 password/deleted_at/微信四列） */
    public function getProfile(int $userId): array
    {
        return $this->narrowRow($this->findOrFail($userId));
    }

    /** @param array<string, mixed> $data 已校验：nickname/avatar/gender/birthday 均可选 */
    public function updateProfile(int $userId, array $data): bool
    {
        $update = array_intersect_key($data, array_flip(['nickname', 'avatar', 'gender', 'birthday']));
        if ($update === []) {
            return true;
        }

        return $this->userRepository->update($userId, $update);
    }

    /**
     * 改密成功后当前会话失效（spec §4.2）：其余端已登录的 token 也一起失效，前端下次请求收到 401
     * 回登录页。版本号和改密在同一个事务里自增：事务回滚时吊销也回去。
     */
    public function changePassword(int $userId, string $old, string $new): bool
    {
        $user = $this->userRepository->findWithPassword($userId) ?? throw new NotFoundException();
        if (!password_verify($old, (string) $user['password'])) {
            throw new BusinessException(lang('auth.old_password_error'));
        }

        return $this->runInTransaction(function () use ($userId, $new): bool {
            $ok = $this->userRepository->update($userId, ['password' => password_hash($new, PASSWORD_DEFAULT)]);
            TokenVersion::bump($userId, 'user');

            return $ok;
        });
    }

    /** @return string 十进制字符串，如 "0.00"（User 模型对 balance 列做 decimal:2 转换） */
    public function getBalance(int $userId): string
    {
        return (string) $this->findOrFail($userId)['balance'];
    }

    public function getPoints(int $userId): int
    {
        return (int) $this->findOrFail($userId)['points'];
    }

    /** @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}} */
    public function getBalanceLogs(int $userId, int $page, int $limit): array
    {
        return $this->balanceLogRepository->getUserList($userId, $page, $limit);
    }

    /** @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}} */
    public function getPointsLogs(int $userId, int $page, int $limit): array
    {
        return $this->pointsLogRepository->getUserList($userId, $page, $limit);
    }

    /** @return array<string, mixed> */
    private function findOrFail(int $userId): array
    {
        return $this->userRepository->find($userId) ?? throw new NotFoundException();
    }

    /**
     * 与 `app\service\user\UserAuthService::narrowRow()`（Task 6）是同一份收窄逻辑，各自维护——
     * 改一处要同步改另一处。
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function narrowRow(array $user): array
    {
        unset($user['password'], $user['deleted_at'], $user['openid'], $user['oa_openid'], $user['unionid'], $user['mini_openid']);

        return $user;
    }
}
