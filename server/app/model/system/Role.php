<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 角色。is_system=1 为系统角色（超管）；data_scope 取值见 core\datascope\DataScope。 */
class Role extends Model
{
    use SoftDeletes;

    private const DATA_SCOPE_TEXT = [1 => '全部数据', 2 => '本部门数据', 3 => '本部门及以下数据', 4 => '仅本人数据', 5 => '自定义数据'];

    protected $table = 'roles';

    /** @var array<string, string> */
    protected $casts = [
        'data_scope' => 'integer',
        'is_system'  => 'boolean',
        'status'     => 'integer',
        'sort'       => 'integer',
    ];

    /** @var list<string> */
    protected $appends = ['status_text', 'data_scope_text', 'is_system_text'];

    public function getStatusTextAttribute(): string
    {
        return (int) ($this->attributes['status'] ?? 0) === 1 ? '正常' : '禁用';
    }

    public function getDataScopeTextAttribute(): string
    {
        return self::DATA_SCOPE_TEXT[(int) ($this->attributes['data_scope'] ?? 0)] ?? '';
    }

    public function getIsSystemTextAttribute(): string
    {
        return (int) ($this->attributes['is_system'] ?? 0) === 1 ? '是' : '否';
    }

    /** @return BelongsToMany<Admin, $this> */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'admin_roles', 'role_id', 'admin_id')->withTimestamps();
    }

    /** @return BelongsToMany<Menu, $this> */
    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'role_menus', 'role_id', 'menu_id')->withTimestamps();
    }

    /**
     * 自定义数据范围（data_scope=5）的部门。
     *
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'role_departments', 'role_id', 'department_id')->withTimestamps();
    }
}
