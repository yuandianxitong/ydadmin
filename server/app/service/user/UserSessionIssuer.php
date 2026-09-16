<?php

declare(strict_types=1);

namespace app\service\user;

use app\repository\user\UserRepository;
use core\auth\TokenManager;
use core\auth\TokenVersion;
use core\base\Service;
use core\exception\NotFoundException;
use DI\Attribute\Inject;
use support\Log;

/**
 * C 端登录成功后的统一签发（M6a 设计决定 8）：逻辑逐字搬自 M5a UserAuthService::loginSuccess()，
 * 账号密码 / 短信登录 / 注册与四种微信登录共用，保证「更新最后登录 + 带 ver 的 user token + 四字段 user_info」
 * 只有一份实现。
 */
class UserSessionIssuer extends Service
{
    #[Inject]
    protected UserRepository $userRepository;

    /**
     * @return array{token: string, user_info: array{id: int, nickname: string, avatar: ?string, mobile: ?string}}
     */
    public function issue(int $userId, string $ip): array
    {
        try {
            $this->userRepository->updateLastLogin($userId, $ip);
        } catch (\Throwable $e) {
            Log::warning('更新会员最后登录信息失败：' . $e->getMessage());
        }
        $user = $this->userRepository->find($userId) ?? throw new NotFoundException();
        $token = TokenManager::scope('user')->generate([
            'user_id' => $userId,
            'ver'     => TokenVersion::current($userId, 'user'),
        ]);

        return [
            'token'     => $token,
            'user_info' => [
                'id'       => $userId,
                'nickname' => (string) $user['nickname'],
                'avatar'   => $user['avatar'] !== null ? (string) $user['avatar'] : null,
                'mobile'   => $user['mobile'] !== null ? (string) $user['mobile'] : null,
            ],
        ];
    }
}
