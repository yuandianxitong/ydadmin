<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\AdminLoginLogRepository;
use app\repository\system\AdminRepository;
use app\repository\system\DepartmentRepository;
use app\repository\system\MenuRepository;
use app\repository\system\RoleRepository;
use core\auth\Permission;
use core\auth\TokenManager;
use core\auth\TokenVersion;
use core\base\Service;
use core\context\RequestContext;
use core\datascope\DataScope;
use core\datascope\DataScopeResolver;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use core\validation\ValidatorFactory;
use DI\Attribute\Inject;
use support\Log;

class AdminService extends Service
{
    public const SUPER_ADMIN_ID = 1;

    public const SUPER_ROLE_ID = 1;

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

    #[Inject]
    protected RoleRepository $roleRepository;

    #[Inject]
    protected SystemConfigService $systemConfigService;

    #[Inject]
    protected Permission $permission;

    #[Inject]
    protected DataScopeResolver $dataScopeResolver;

    #[Inject]
    protected DepartmentRepository $departmentRepository;

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

    /** 密码规则（不含 required/nullable）：最短长度读 password_min_length（登录安全配置全部生效）。 */
    public function passwordRule(): string
    {
        $min = max(1, min(20, (int) $this->systemConfigService->getConfigValue('password_min_length', 6)));

        return "string|min:{$min}|max:20";
    }

    /**
     * 列表（受数据权限约束，分页总数按范围统计）。
     *
     * @param array<string, mixed> $params keyword、status
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getAdminList(array $params, int $page, int $limit): array
    {
        $where = [];
        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $where[] = [static function ($query) use ($like): void {
                $query->where('admins.username', 'like', $like)
                    ->orWhere('admins.email', 'like', $like)
                    ->orWhere('admins.nickname', 'like', $like);
            }];
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['admins.status', '=', (int) $params['status']];
        }

        return $this->adminRepository->getListWithRoles($where, $page, $limit);
    }

    /**
     * @param array<string, mixed> $data 控制器 validate() 的返回值
     * @return array<string, mixed>
     */
    public function createAdmin(array $data): array
    {
        $this->assertUniqueIdentity($data, 0);
        $roleIds = $this->validRoleIds((array) ($data['role_ids'] ?? []));
        $this->assertCanAssignRoles($roleIds);
        $row = [
            'username'      => $data['username'],
            'email'         => $data['email'],
            'mobile'        => $data['mobile'] ?? null,
            'password'      => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'nickname'      => $data['nickname'] ?? $data['username'],
            'avatar'        => $data['avatar'] ?? null,
            'department_id' => $this->validDepartmentId($data['department_id'] ?? null),
            'position'      => $data['position'] ?? null,
            'status'        => (int) ($data['status'] ?? 1),
        ];

        return $this->runInTransaction(function () use ($row, $roleIds): array {
            $admin = $this->adminRepository->create($row); // created_by 由 Repository 自动填
            $this->adminRepository->assignRoles((int) $admin['id'], $roleIds);

            return $admin;
        });
    }

    /** @param array<string, mixed> $data 控制器 validate() 的返回值（字段均可选） */
    public function updateAdmin(int $id, array $data): void
    {
        $this->findOrFail($id);
        $this->assertCanModify($id);
        $this->assertUniqueIdentity($data, $id);
        $disabling = isset($data['status']) && (int) $data['status'] === 0;
        if ($disabling) {
            $this->assertCanDisable($id);
        }

        $update = array_filter(
            array_intersect_key($data, array_flip(['username', 'email', 'mobile', 'nickname', 'avatar', 'position', 'status'])),
            static fn ($value) => $value !== null
        );
        if (array_key_exists('department_id', $data)) {
            $update['department_id'] = $this->validDepartmentId($data['department_id']);
        }
        $passwordChanged = !empty($data['password']);
        if ($passwordChanged) {
            $update['password'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }
        $update['updated_by'] = RequestContext::actingUser() ?: null;
        $roleIds = array_key_exists('role_ids', $data) ? $this->validRoleIds((array) ($data['role_ids'] ?? [])) : null;
        if ($roleIds !== null) {
            $this->assertCanAssignRoles($roleIds);
        }

        $this->runInTransaction(function () use ($id, $update, $roleIds, $passwordChanged, $disabling): void {
            $this->adminRepository->update($id, $update);
            if ($roleIds !== null) {
                $this->adminRepository->assignRoles($id, $roleIds);
            }
            $this->afterCommit(fn () => $this->forgetAdmin($id, $passwordChanged || $disabling));
        });
    }

    /** 软删除。范围外或不存在 → 404；超管、本人不可删。 */
    public function deleteAdmin(int $id): void
    {
        $info = $this->getAdminInfo($id);
        if (!empty($info['is_super'])) {
            throw new BusinessException(lang('auth.super_admin_no_delete'));
        }
        if ($id === RequestContext::actingUser()) {
            throw new BusinessException(lang('auth.cannot_delete_self'));
        }

        $this->runInTransaction(function () use ($id): void {
            $this->adminRepository->delete($id);
            $this->afterCommit(fn () => $this->forgetAdmin($id, true));
        });
    }

    /**
     * 批量删除：范围外的 ID 不生效（spec §5.3），其余逐个按单删规则，任一失败整体回滚。
     *
     * @param array<int, int|string> $ids
     * @return int 实际删除数
     */
    public function batchDeleteAdmins(array $ids): int
    {
        $visible = $this->adminRepository->visibleIds($ids);
        $this->runInTransaction(function () use ($visible): void {
            foreach ($visible as $id) {
                $this->deleteAdmin($id);
            }
        });

        return count($visible);
    }

    public function updateStatus(int $id, int $status): void
    {
        $this->findOrFail($id);
        if ($status === 0) {
            $this->assertCanDisable($id);
        }

        $this->runInTransaction(function () use ($id, $status): void {
            $this->adminRepository->update($id, ['status' => $status, 'updated_by' => RequestContext::actingUser() ?: null]);
            $this->afterCommit(fn () => $this->forgetAdmin($id, $status === 0));
        });
    }

    /** 管理员重置他人密码（无需旧密码）。 */
    public function resetPassword(int $id, string $password): void
    {
        $this->findOrFail($id);
        $this->assertCanModify($id);

        $this->runInTransaction(function () use ($id, $password): void {
            $this->adminRepository->update($id, [
                'password'   => password_hash($password, PASSWORD_DEFAULT),
                'updated_by' => RequestContext::actingUser() ?: null,
            ]);
            $this->afterCommit(fn () => $this->forgetAdmin($id, true));
        });
    }

    /** 修改自己的密码；成功后当前会话也失效，前端收到 401 回登录页（spec §4.4）。 */
    public function changePassword(int $id, string $oldPassword, string $newPassword): void
    {
        $admin = $this->adminRepository->findWithPassword($id) ?? throw new NotFoundException();
        if (!password_verify($oldPassword, (string) $admin['password'])) {
            throw new BusinessException(lang('auth.old_password_error'));
        }

        $this->runInTransaction(function () use ($id, $newPassword): void {
            DataScope::bypass(fn (): bool => $this->adminRepository->update($id, ['password' => password_hash($newPassword, PASSWORD_DEFAULT)]));
            $this->afterCommit(fn () => $this->forgetAdmin($id, true));
        });
    }

    /** @return array<string, mixed> */
    private function findOrFail(int $id): array
    {
        return $this->adminRepository->find($id) ?? throw new NotFoundException();
    }

    /** @param array<string, mixed> $data */
    private function assertUniqueIdentity(array $data, int $excludeId): void
    {
        if (!empty($data['username']) && $this->adminRepository->existsUsername((string) $data['username'], $excludeId)) {
            throw new BusinessException(lang('auth.username_exists'));
        }
        if (!empty($data['email']) && $this->adminRepository->existsEmail((string) $data['email'], $excludeId)) {
            throw new BusinessException(lang('auth.email_exists'));
        }
    }

    /** 超管与本人不能禁用（spec §4.6）。 */
    private function assertCanDisable(int $id): void
    {
        if (!empty($this->getAdminInfo($id)['is_super'])) {
            throw new BusinessException(lang('auth.super_admin_no_disable'));
        }
        if ($id === RequestContext::actingUser()) {
            throw new BusinessException(lang('auth.cannot_disable_self'));
        }
    }

    /** 防提权：非超管不能编辑、重置超管账号。无管理员身份（CLI）不受限。 */
    private function assertCanModify(int $id): void
    {
        $actor = RequestContext::actingUser();
        if ($actor > 0 && $actor !== $id && $this->permission->isSuperAdmin($id) && !$this->permission->isSuperAdmin($actor)) {
            throw new BusinessException(lang('auth.super_admin_no_modify'));
        }
    }

    /**
     * 防提权：系统角色只能由超管分配。无管理员身份（CLI）不受限。
     *
     * @param list<int> $roleIds
     */
    private function assertCanAssignRoles(array $roleIds): void
    {
        $actor = RequestContext::actingUser();
        if ($actor > 0 && !$this->permission->isSuperAdmin($actor) && $this->roleRepository->containsSystemRole($roleIds)) {
            throw new BusinessException(lang('business.system_role_no_assign'));
        }
    }

    /**
     * @param array<int, mixed> $roleIds
     * @return list<int>
     */
    private function validRoleIds(array $roleIds): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if (count($this->roleRepository->existingIds($roleIds)) !== count($roleIds)) {
            throw new BusinessException(lang('business.role_not_found'));
        }

        return $roleIds;
    }

    private function validDepartmentId(mixed $value): ?int
    {
        $id = (int) ($value ?? 0);
        if ($id <= 0) {
            return null;
        }
        if ($this->departmentRepository->existingIds([$id]) === []) {
            throw new BusinessException(lang('business.dept_not_found'));
        }

        return $id;
    }

    /** 管理员变更后的缓存失效；$revokeTokens 为真时自增 token 版本号（禁用、删除、改密码）。 */
    private function forgetAdmin(int $id, bool $revokeTokens): void
    {
        $this->permission->clearUserCache($id);
        $this->dataScopeResolver->forget($id);
        if ($revokeTokens) {
            TokenVersion::bump($id);
        }
    }

    /**
     * 建立或重置 id=1 的超级管理员（spec §3.3）：挂超管角色，自增 token 版本号（该账号已签发的 token 全部失效）。
     * admin:init 命令与 M8 安装向导共用。
     *
     * @return array{id: int, username: string, created: bool}
     */
    public function initSuperAdmin(string $username, string $password, ?string $email, ?string $nickname): array
    {
        ValidatorFactory::validate(
            ['username' => $username, 'password' => $password, 'email' => $email, 'nickname' => $nickname],
            [
                'username' => 'required|string|min:3|max:20|alpha_dash:ascii',
                'password' => 'required|' . $this->passwordRule(),
                'email'    => 'nullable|email|max:100',
                'nickname' => 'nullable|string|max:50',
            ]
        );
        if ($this->adminRepository->existsUsername($username, self::SUPER_ADMIN_ID)) {
            throw new BusinessException(lang('auth.username_exists'));
        }
        if ($email !== null && $this->adminRepository->existsEmail($email, self::SUPER_ADMIN_ID)) {
            throw new BusinessException(lang('auth.email_exists'));
        }
        if ($this->roleRepository->existingIds([self::SUPER_ROLE_ID]) === []) {
            throw new BusinessException(lang('business.role_not_found'));
        }

        $data = array_filter([
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'email'    => $email,
            'nickname' => $nickname,
            'status'   => 1,
        ], static fn ($value) => $value !== null);

        $created = $this->runInTransaction(function () use ($data, $username): bool {
            $created = $this->adminRepository->upsertById(self::SUPER_ADMIN_ID, $data, ['nickname' => $username]);
            $this->adminRepository->assignRoles(self::SUPER_ADMIN_ID, [self::SUPER_ROLE_ID]);
            $this->afterCommit(function (): void {
                TokenVersion::bump(self::SUPER_ADMIN_ID);
                $this->permission->clearUserCache(self::SUPER_ADMIN_ID);
                $this->dataScopeResolver->forget(self::SUPER_ADMIN_ID);
            });

            return $created;
        });

        return ['id' => self::SUPER_ADMIN_ID, 'username' => $username, 'created' => $created];
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
