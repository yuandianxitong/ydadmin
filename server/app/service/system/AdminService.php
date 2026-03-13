<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\system;

use app\repository\system\AdminRepository;
use app\repository\system\MenuRepository;
use app\repository\system\RoleRepository;
use core\base\Service;
use core\auth\TokenManager;
use core\exception\BusinessException;
use think\facade\Db;

class AdminService extends Service
{
    protected AdminRepository $adminRepository;
    protected RoleRepository $roleRepository;
    protected MenuRepository $menuRepository;
    protected TokenManager $tokenManager;
    protected MenuService $menuService;

    /**
     * 管理员登录
     */
    public function login(string $username, string $password, string $ip, string $userAgent): array
    {
        $loginContext = ['username' => $username, 'ip' => $ip, 'user_agent' => $userAgent];

        // 查找管理员
        $admin = $this->adminRepository->findByUsername($username);
        if (!$admin) {
            $this->trigger('admin.login.failed', $loginContext + ['message' => '用户名不存在']);
            throw new BusinessException(lang('auth.login_failed'));
        }

        // 检查账户状态
        if ($admin['status'] != 1) {
            $this->trigger('admin.login.failed', $loginContext + ['admin_id' => $admin['id'], 'message' => '账户已被禁用']);
            throw new BusinessException(lang('auth.account_disabled'));
        }

        // 验证密码
        if (!password_verify($password, $admin['password'])) {
            $this->trigger('admin.login.failed', $loginContext + ['admin_id' => $admin['id'], 'message' => '密码错误']);
            throw new BusinessException(lang('auth.login_failed'));
        }

        // 生成Token
        $token = $this->tokenManager->generate([
            'admin_id' => $admin['id'],
            'username' => $admin['username'],
            'type' => 'admin'
        ]);

        // 获取管理员信息（包含角色权限）
        $adminInfo = $this->getAdminInfo((int)$admin['id']);

        // 登录成功事件（更新登录信息 + 记录日志 由 Listener 处理）
        $this->trigger('admin.login.success', $loginContext + ['admin_id' => $admin['id']]);

        return [
            'token' => $token,
            'admin' => $adminInfo
        ];
    }

    /**
     * 获取管理员信息
     */
    public function getAdminInfo(int $adminId): array
    {
        $admin = $this->adminRepository->getDetailWithPermissions($adminId);

        if (!$admin) {
            throw new BusinessException(lang('auth.admin_not_found'));
        }

        // 获取权限列表（统一从菜单获取）
        $permissions = [];
        $menuIds = [];

        foreach ($admin['roles'] as $role) {
            foreach ($role['menus'] as $menu) {
                $menuIds[] = $menu['id'];
            }
        }

        // 如果是超级管理员（ID为1），拥有所有菜单权限
        if ($admin['id'] == 1) {
            $menuIds = $this->menuRepository->getAllEnabledMenuIds();
        }

        // 从菜单按钮中获取权限标识
        $permissions = $this->menuService->getButtonPermissions(array_unique($menuIds));

        $admin['permissions'] = array_unique($permissions);
        $admin['menu_ids'] = array_unique($menuIds);

        // 移除敏感信息
        unset($admin['password']);

        return $admin;
    }

    /**
     * 获取管理员列表
     */
    public function getAdminList(array $params): array
    {
        $where = [];

        // 搜索条件
        if (!empty($params['keyword'])) {
            $where[] = ['username|email|nickname', 'like', '%' . $params['keyword'] . '%'];
        }

        if (isset($params['status'])) {
            $where[] = ['status', '=', $params['status']];
        }

        if (!empty($params['department'])) {
            $where[] = ['department', 'like', '%' . $params['department'] . '%'];
        }

        $page = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 15);

        return $this->adminRepository->getListWithRoles($where, $page, $limit);
    }

    /**
     * 创建管理员
     */
    public function createAdmin(array $data): array
    {
        // 验证用户名和邮箱唯一性
        if ($this->adminRepository->existsUsername($data['username'])) {
            throw new BusinessException(lang('auth.username_exists'));
        }

        if ($this->adminRepository->existsEmail($data['email'])) {
            throw new BusinessException(lang('auth.email_exists'));
        }

        Db::startTrans();
        try {
            // 创建管理员
            $adminData = [
                'username' => $data['username'],
                'email' => $data['email'],
                'mobile' => $data['mobile'] ?? '',
                'password' => $data['password'],
                'nickname' => $data['nickname'] ?? $data['username'],
                'avatar' => $data['avatar'] ?? '',
                'department' => $data['department'] ?? '',
                'position' => $data['position'] ?? '',
                'status' => $data['status'] ?? 1,
                'created_by' => $data['created_by'] ?? 0,
            ];

            $admin = $this->adminRepository->create($adminData);

            // 分配角色
            if (!empty($data['role_ids'])) {
                $this->adminRepository->assignRoles($admin['id'], $data['role_ids']);
            }

            Db::commit();

            $this->log('创建管理员成功', ['admin_id' => $admin['id']]);

            return $admin;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 更新管理员
     */
    public function updateAdmin(int $id, array $data): bool
    {
        $admin = $this->adminRepository->find($id);
        if (!$admin) {
            throw new BusinessException(lang('auth.admin_not_found'));
        }

        // 验证用户名和邮箱唯一性
        if (isset($data['username']) && $this->adminRepository->existsUsername($data['username'], $id)) {
            throw new BusinessException(lang('auth.username_exists'));
        }

        if (isset($data['email']) && $this->adminRepository->existsEmail($data['email'], $id)) {
            throw new BusinessException(lang('auth.email_exists'));
        }

        Db::startTrans();
        try {
            // 更新基本信息
            $updateData = array_filter([
                'username' => $data['username'] ?? null,
                'email' => $data['email'] ?? null,
                'mobile' => $data['mobile'] ?? null,
                'nickname' => $data['nickname'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'department' => $data['department'] ?? null,
                'position' => $data['position'] ?? null,
                'status' => $data['status'] ?? null,
                'updated_by' => $data['updated_by'] ?? 0,
            ], function($value) {
                return $value !== null;
            });

            // 如果有密码更新
            if (!empty($data['password'])) {
                $updateData['password'] = $data['password'];
            }

            $result = $this->adminRepository->update($id, $updateData);

            // 更新角色
            if (isset($data['role_ids'])) {
                $this->adminRepository->assignRoles($id, $data['role_ids']);
            }

            Db::commit();

            $this->log('更新管理员成功', ['admin_id' => $id]);

            return $result;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 删除管理员
     */
    public function deleteAdmin(int $id): bool
    {
        $admin = $this->adminRepository->find($id);
        if (!$admin) {
            throw new BusinessException(lang('auth.admin_not_found'));
        }

        // 超级管理员不能删除
        if ($id == 1) {
            throw new BusinessException(lang('auth.super_admin_no_delete'));
        }

        // 不能删除自己
        $currentAdminId = request()->userId ?? 0;
        if ($id == $currentAdminId) {
            throw new BusinessException(lang('auth.cannot_delete_self'));
        }

        $result = $this->adminRepository->delete($id);

        if ($result) {
            $this->log('删除管理员成功', ['admin_id' => $id]);
        }

        return $result;
    }

    /**
     * 更新管理员状态
     */
    public function updateStatus(int $id, int $status): bool
    {
        $admin = $this->adminRepository->find($id);
        if (!$admin) {
            throw new BusinessException(lang('auth.admin_not_found'));
        }

        // 超级管理员不能禁用
        if ($id == 1 && $status == 0) {
            throw new BusinessException(lang('auth.super_admin_no_disable'));
        }

        // 不能禁用自己
        $currentAdminId = request()->userId ?? 0;
        if ($id == $currentAdminId && $status == 0) {
            throw new BusinessException(lang('auth.cannot_disable_self'));
        }

        $result = $this->adminRepository->update($id, ['status' => $status]);

        if ($result) {
            $action = $status == 1 ? '启用' : '禁用';
            $this->log($action . '管理员成功', ['admin_id' => $id]);
        }

        return $result;
    }

    /**
     * 重置密码
     */
    public function resetPassword(int $id, string $password): bool
    {
        $admin = $this->adminRepository->find($id);
        if (!$admin) {
            throw new BusinessException(lang('auth.admin_not_found'));
        }

        $result = $this->adminRepository->update($id, ['password' => $password]);

        if ($result) {
            $this->log('重置密码成功', ['admin_id' => $id]);
        }

        return $result;
    }

    /**
     * 修改密码
     */
    public function changePassword(int $id, string $oldPassword, string $newPassword): bool
    {
        $admin = $this->adminRepository->find($id);
        if (!$admin) {
            throw new BusinessException(lang('auth.admin_not_found'));
        }

        // 验证原密码
        if (!password_verify($oldPassword, $admin['password'])) {
            throw new BusinessException(lang('auth.old_password_error'));
        }

        $result = $this->adminRepository->update($id, ['password' => $newPassword]);

        if ($result) {
            $this->log('修改密码成功', ['admin_id' => $id]);
        }

        return $result;
    }

}
