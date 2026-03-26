<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\system;

use app\model\system\Admin;
use core\base\Repository;
use think\Model;

class AdminRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Admin();
    }

    /**
     * 根据用户名查找管理员
     */
    public function findByUsername(string $username): ?array
    {
        $result = $this->model->where('username', $username)->find();
        if (!$result) {
            return null;
        }
        // 登录校验需要访问加密密码，临时清空隐藏字段再转为数组
        $result->hidden([]);
        return $result->toArray();
    }

    /**
     * 根据邮箱查找管理员
     */
    public function findByEmail(string $email): ?array
    {
        $result = $this->model->where('email', $email)->find();
        return $result ? $result->toArray() : null;
    }

    /**
     * 获取管理员列表（包含角色）
     */
    public function getListWithRoles(array $where = [], int $page = 1, int $limit = 15): array
    {
        $query = $this->model->with(['roles'])->where($where);

        $total = $query->count();
        $list = $query->page($page, $limit)
            ->order('created_at desc')
            ->select()
            ->toArray();

        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => ceil($total / $limit)
            ]
        ];
    }

    /**
     * 获取管理员详情（包含角色和权限）
     */
    public function getDetailWithPermissions(int $id): ?array
    {
        $admin = $this->model->find($id);

        if (!$admin) {
            return null;
        }

        // 手动构建完整的数据结构
        $adminData = $admin->toArray();
        $adminData['roles'] = [];

        // 获取角色信息
        $roles = $admin->roles;
        foreach ($roles as $role) {
            $roleData = $role->toArray();

            // 手动查询菜单避免ORM问题
            $menus = \think\facade\Db::table('role_menus')
                ->alias('rm')
                ->join('menus m', 'rm.menu_id = m.id')
                ->where('rm.role_id', $role->id)
                ->field('m.*')
                ->select();
            $roleData['menus'] = $menus->toArray();

            $adminData['roles'][] = $roleData;
        }

        return $adminData;
    }

    /**
     * 更新最后登录信息（原子递增 login_count）
     */
    public function updateLastLogin(int $id, string $ip): bool
    {
        return $this->model->where('id', $id)->update([
            'last_login_ip' => $ip,
            'last_login_time' => date('Y-m-d H:i:s'),
            'login_count' => \think\facade\Db::raw('login_count + 1'),
        ]) !== false;
    }

    /**
     * 分配角色
     */
    public function assignRoles(int $adminId, array $roleIds): bool
    {
        $now = date('Y-m-d H:i:s');

        // 清除旧关联
        \think\facade\Db::table('admin_roles')->where('admin_id', $adminId)->delete();

        // 写入新关联（带时间戳）
        if (!empty($roleIds)) {
            $pivotData = array_map(fn(int $roleId) => [
                'admin_id' => $adminId,
                'role_id' => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $roleIds);
            \think\facade\Db::table('admin_roles')->insertAll($pivotData);
        }

        return true;
    }

    /**
     * 检查用户名是否存在
     */
    public function existsUsername(string $username, int $excludeId = 0): bool
    {
        $query = $this->model->where('username', $username);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }

    /**
     * 检查邮箱是否存在
     */
    public function existsEmail(string $email, int $excludeId = 0): bool
    {
        $query = $this->model->where('email', $email);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }
}
