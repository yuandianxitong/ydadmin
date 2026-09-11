<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\AdminLoginLogRepository;
use app\repository\system\AdminRepository;
use app\repository\system\MenuRepository;
use core\auth\TokenManager;
use core\auth\TokenVersion;
use core\base\Service;
use core\datascope\DataScope;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use DI\Attribute\Inject;
use support\Log;

class AdminService extends Service
{
    /**
     * 用户名不存在时也要跑一次 bcrypt（成本与真实校验相同），抵消时序差异——否则「用户名不存在」
     * 分支比「密码错误」分支快得多，攻击者可以靠响应耗时枚举出哪些用户名存在。密文对应明文
     * 是什么不重要，固定即可，用 password_hash('x', PASSWORD_DEFAULT) 生成一次写死在这里。
     */
    private const DUMMY_PASSWORD_HASH = '$2y$12$cSHA1L2hbXvwZHh/q8T3GO9BunJtH1nllHhcbtYGIwuaqUPJPFrU6';

    #[Inject]
    protected AdminRepository $adminRepository;

    #[Inject]
    protected AdminLoginLogRepository $adminLoginLogRepository;

    #[Inject]
    protected MenuRepository $menuRepository;

    #[Inject]
    protected MenuService $menuService;

    /**
     * 登录（spec §4.3）。成功与失败都同步写登录日志；写日志、更新登录信息失败只记日志，不影响登录。
     * 防计时枚举：用户名不存在与密码错误返回同一条消息、跑同一次 bcrypt 成本，响应耗时不可区分；
     * 禁用账户是 TP8 契约要求的独立分支，走自己的消息，不在防枚举范围内。
     *
     * @return array{token: string, admin: array<string, mixed>}
     */
    public function login(string $username, string $password, string $ip, string $userAgent): array
    {
        $admin = $this->adminRepository->findByUsername($username);
        if ($admin === null) {
            password_verify($password, self::DUMMY_PASSWORD_HASH);
            $this->recordLoginLog(0, $username, $ip, $userAgent, false, '用户名不存在');
            throw new BusinessException(lang('auth.login_failed'));
        }
        $adminId = (int) $admin['id'];
        if ((int) $admin['status'] !== 1) {
            $this->recordLoginLog($adminId, $username, $ip, $userAgent, false, '账户已被禁用');
            throw new BusinessException(lang('auth.account_disabled'));
        }
        if (!password_verify($password, (string) $admin['password'])) {
            $this->recordLoginLog($adminId, $username, $ip, $userAgent, false, '密码错误');
            throw new BusinessException(lang('auth.login_failed'));
        }

        $token = $this->issueToken($adminId, (string) $admin['username']);
        try {
            $this->adminRepository->updateLastLogin($adminId, $ip);
        } catch (\Throwable $e) {
            Log::warning('更新最后登录信息失败：' . $e->getMessage());
        }
        $this->recordLoginLog($adminId, $username, $ip, $userAgent, true, lang('messages.login_success'));

        return ['token' => $token, 'admin' => $this->getSelfInfo($adminId)];
    }

    /**
     * 管理员信息（契约 §4.2）：admins 字段（无 password）+ roles[].menus + permissions + menu_ids + is_super。
     * 受数据权限约束：范围外或不存在抛 NotFoundException（spec §5.3）。
     * 超管（任一启用的 is_system 角色）的 menu_ids 为全部启用菜单，permissions 首位为 '*'。
     *
     * @return array<string, mixed>
     */
    public function getAdminInfo(int $adminId): array
    {
        $admin = $this->adminRepository->getDetailWithPermissions($adminId);
        if ($admin === null) {
            throw new NotFoundException();
        }

        $menuIds = [];
        $isSuper = false;
        foreach ((array) $admin['roles'] as $role) {
            if ((int) ($role['status'] ?? 0) !== 1) {
                continue; // 禁用的角色不授予菜单，与 core\auth\Permission 保持一致
            }
            if (!empty($role['is_system'])) {
                $isSuper = true;
            }
            foreach ((array) ($role['menus'] ?? []) as $menu) {
                $menuIds[] = (int) $menu['id'];
            }
        }
        if ($isSuper) {
            $menuIds = $this->menuRepository->getAllEnabledMenuIds();
        }
        $menuIds = array_values(array_unique($menuIds));

        $permissions = $this->menuService->getButtonPermissions($menuIds);
        if ($isSuper) {
            array_unshift($permissions, '*');
        }

        $admin['permissions'] = array_values(array_unique($permissions));
        $admin['menu_ids'] = $menuIds;
        $admin['is_super'] = $isSuper ? 1 : 0;
        unset($admin['password']);

        return $admin;
    }

    /**
     * 当前登录管理员看自己的资料，不受数据权限约束（没有部门的管理员也要能登录）。
     *
     * @return array<string, mixed>
     */
    public function getSelfInfo(int $adminId): array
    {
        return DataScope::bypass(fn (): array => $this->getAdminInfo($adminId));
    }

    protected function issueToken(int $adminId, string $username): string
    {
        return TokenManager::scope('admin')->generate([
            'admin_id' => $adminId,
            'username' => $username,
            'ver'      => TokenVersion::current($adminId),
        ]);
    }

    private function recordLoginLog(int $adminId, string $username, string $ip, string $userAgent, bool $success, string $message): void
    {
        try {
            $this->adminLoginLogRepository->record([
                'admin_id'      => $adminId,
                'username'      => $username,
                'ip'            => $ip,
                'user_agent'    => $userAgent,
                'login_result'  => $success,
                'login_message' => $message,
            ]);
        } catch (\Throwable $e) {
            Log::warning('写登录日志失败：' . $e->getMessage());
        }
    }
}
