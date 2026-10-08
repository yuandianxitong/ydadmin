<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** 管理员。没有 is_super 列：拥有 is_system=1 角色的即超管（运行时计算）。 */
class Admin extends Model
{
    use SoftDeletes;

    protected $table = 'admins';

    protected $hidden = ['password', 'token_version'];

    /** @var array<string, string> */
    protected $casts = [
        'status'          => 'integer',
        'login_count'     => 'integer',
        'department_id'   => 'integer',
        'last_login_time' => 'datetime',
    ];

    /** @var list<string> */
    protected $appends = ['status_text', 'avatar_url'];

    public function getStatusTextAttribute(): string
    {
        return (int) ($this->attributes['status'] ?? 0) === 1 ? '正常' : '禁用';
    }

    /** TP8 契约字段；M1c 接入存储后按驱动拼完整 URL，目前原样返回。 */
    public function getAvatarUrlAttribute(): string
    {
        return (string) ($this->attributes['avatar'] ?? '');
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_roles', 'admin_id', 'role_id')->withTimestamps();
    }
}
