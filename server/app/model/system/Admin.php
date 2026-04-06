<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use think\model\relation\BelongsToMany;
use think\model\relation\HasMany;

class Admin extends Model
{
    protected $table = 'admins';

    protected $fillable = [
        'username', 'email', 'mobile', 'password', 'nickname', 'avatar',
        'department_id', 'department', 'position', 'status', 'last_login_ip', 'last_login_time',
        'login_count', 'created_by', 'updated_by'
    ];

    protected $hidden = ['password', 'deleted_at'];

    protected $type = [
        'status' => 'integer',
        'login_count' => 'integer',
        'last_login_time' => 'datetime',
    ];

    protected $append = ['status_text', 'avatar_url'];

    // 关联角色
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_roles', 'role_id', 'admin_id');
    }

    // 登录日志
    public function loginLogs(): HasMany
    {
        return $this->hasMany(AdminLoginLog::class, 'admin_id');
    }

    // 操作日志
    public function operationLogs(): HasMany
    {
        return $this->hasMany(AdminOperationLog::class, 'admin_id');
    }

    // 访问器
    public function getStatusTextAttr($value, $data): string
    {
        if (!isset($data['status'])) return '';
        $status = [1 => '正常', 0 => '禁用'];
        return $status[$data['status']] ?? '未知';
    }

    public function getAvatarUrlAttr($value, $data): string
    {
        if (!isset($data['avatar']) || empty($data['avatar'])) {
            return '/static/images/default-avatar.png';
        }

        if (strpos($data['avatar'], 'http') === 0) {
            return $data['avatar'];
        }

        return request()->domain() . $data['avatar'];
    }

    /**
     * 验证密码
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    /**
     * 获取管理员权限（从菜单按钮获取）
     */
    public function getPermissions(): array
    {
        $permissions = [];
        foreach ($this->roles as $role) {
            foreach ($role->menus as $menu) {
                if ($menu->type == 3 && !empty($menu->permission)) {
                    $permissions[] = $menu->permission;
                }
            }
        }
        return array_unique($permissions);
    }

    /**
     * 获取管理员菜单
     */
    public function getMenus(): array
    {
        $menus = [];
        foreach ($this->roles as $role) {
            foreach ($role->menus as $menu) {
                $menus[$menu->id] = $menu->toArray();
            }
        }
        return array_values($menus);
    }

    /**
     * 检查权限
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();
        return in_array($permission, $permissions) || in_array('*', $permissions);
    }

    /**
     * 检查角色
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * 更新登录信息
     */
    public function updateLoginInfo(string $ip): void
    {
        $this->save([
            'last_login_ip' => $ip,
            'last_login_time' => date('Y-m-d H:i:s'),
            'login_count' => $this->login_count + 1
        ]);
    }
}
