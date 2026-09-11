<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\Admin;
use core\base\Model;
use core\base\Repository;
use core\datascope\DataScope;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use support\Db;

/** 管理员仓储。受数据权限约束（部门列 department_id，归属人为本人）；认证查找、查重与部门删除保护经 DataScope::bypass() 看全表。 */
class AdminRepository extends Repository
{
    /** @var list<string> */
    protected array $sortable = ['id', 'created_at', 'updated_at', 'last_login_time'];

    protected bool $dataScoped = true;

    /** 管理员表的数据归属人就是管理员本人（spec §5.5）。 */
    protected string $ownerColumn = 'id';

    protected ?string $deptColumn = 'department_id';

    protected function getModel(): Model
    {
        return new Admin();
    }

    /**
     * 按用户名查找（含 password，仅供登录校验）。
     *
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        return DataScope::bypass(fn (): ?array => $this->query()->where($this->qualify('username'), $username)->first()?->makeVisible('password')->toArray());
    }

    /**
     * 按 id 查找（含 password，仅供修改密码时校验旧密码）。
     *
     * @return array<string, mixed>|null
     */
    public function findWithPassword(int $id): ?array
    {
        return DataScope::bypass(fn (): ?array => $this->query()->where($this->qualify('id'), $id)->first()?->makeVisible('password')->toArray());
    }

    /**
     * @param array<int|string, mixed> $where
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getListWithRoles(array $where, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query()->with('roles')->where($where);
        $total = (clone $query)->count();
        $list = $query->orderBy($this->qualify('created_at'), 'desc')->orderBy($this->qualify('id'), 'desc')
            ->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 详情（含角色及角色下的菜单）。
     *
     * @return array<string, mixed>|null
     */
    public function getDetailWithPermissions(int $id): ?array
    {
        return $this->query()->with('roles.menus')->where($this->qualify('id'), $id)->first()?->toArray();
    }

    public function updateLastLogin(int $id, string $ip): bool
    {
        return DataScope::bypass(fn (): bool => $this->query()->where($this->qualify('id'), $id)->update([
            'last_login_ip'   => $ip,
            'last_login_time' => date('Y-m-d H:i:s'),
            'login_count'     => Db::raw('login_count + 1'),
        ]) > 0);
    }

    /**
     * 全量覆盖管理员的角色。
     *
     * @param array<int, int> $roleIds
     */
    public function assignRoles(int $adminId, array $roleIds): void
    {
        $now = date('Y-m-d H:i:s');
        Db::table('admin_roles')->where('admin_id', $adminId)->delete();
        $rows = array_map(static fn (int $roleId): array => [
            'admin_id'   => $adminId,
            'role_id'    => $roleId,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_values(array_unique(array_map('intval', $roleIds))));
        if ($rows !== []) {
            Db::table('admin_roles')->insert($rows);
        }
    }

    /** 用户名是否已占用。含软删行：唯一索引对软删行同样生效。 */
    public function existsUsername(string $username, int $excludeId = 0): bool
    {
        return DataScope::bypass(function () use ($username, $excludeId): bool {
            $query = $this->query()->withoutGlobalScope(SoftDeletingScope::class)->where($this->qualify('username'), $username);
            if ($excludeId > 0) {
                $query->where($this->qualify('id'), '<>', $excludeId);
            }

            return $query->exists();
        });
    }

    /** 邮箱是否已占用。含软删行。 */
    public function existsEmail(string $email, int $excludeId = 0): bool
    {
        return DataScope::bypass(function () use ($email, $excludeId): bool {
            $query = $this->query()->withoutGlobalScope(SoftDeletingScope::class)->where($this->qualify('email'), $email);
            if ($excludeId > 0) {
                $query->where($this->qualify('id'), '<>', $excludeId);
            }

            return $query->exists();
        });
    }

    /** 部门下是否还有（未删除的）管理员——部门删除保护。 */
    public function existsByDepartment(int $departmentId): bool
    {
        return DataScope::bypass(fn (): bool => $this->query()->where($this->qualify('department_id'), $departmentId)->exists());
    }

    /**
     * 给定 ID 里当前可见的那些（批量操作先经它过滤：范围外的 ID 不生效，spec §5.3）。
     *
     * @param array<int, int|string> $ids
     * @return list<int>
     */
    public function visibleIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_values(array_map('intval', $this->query()->whereIn($this->qualify('id'), $ids)->pluck($this->qualify('id'))->all()));
    }

    /**
     * 按 id 插入或更新（更新时恢复软删行）。只给 admin:init 用——业务新增走 create()（自增 id）。
     *
     * @param array<string, mixed> $data       插入与更新都写的列
     * @param array<string, mixed> $insertOnly 仅插入时写的列
     * @return bool 是否新插入
     */
    public function upsertById(int $id, array $data, array $insertOnly = []): bool
    {
        $now = date('Y-m-d H:i:s');
        if (Db::table('admins')->where('id', $id)->exists()) {
            Db::table('admins')->where('id', $id)->update($data + ['deleted_at' => null, 'updated_at' => $now]);

            return false;
        }
        Db::table('admins')->insert(['id' => $id] + $data + $insertOnly + ['created_at' => $now, 'updated_at' => $now]);

        return true;
    }
}
