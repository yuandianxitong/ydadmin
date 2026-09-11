<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 部门（departments 表）。`admins.department_id` 是平行字段（无外键约束），
 * `admins()` 关系仅供只读聚合/反查使用。
 */
class Department extends Model
{
    use SoftDeletes;

    protected $table = 'departments';

    /** @var array<string, string> */
    protected $casts = [
        'parent_id' => 'integer',
        'status'    => 'integer',
        'sort'      => 'integer',
    ];

    /** @var array<int, string> */
    protected $appends = ['status_text'];

    /**
     * 状态文案计算属性（同 AdminLoginLog::getLoginResultTextAttribute 手法，
     * Eloquent $appends + accessor，toArray() 自动带出，供前端直接渲染）。
     */
    public function getStatusTextAttribute(): string
    {
        if (!array_key_exists('status', $this->attributes)) {
            return '';
        }

        return (int) $this->attributes['status'] === 1 ? '启用' : '禁用';
    }

    /**
     * 子部门（按 sort/id 升序）。
     *
     * @return HasMany<Department, $this>
     */
    public function children(): HasMany
    {
        $relation = $this->hasMany(self::class, 'parent_id');
        $relation->orderBy('sort')->orderBy('id');

        return $relation;
    }

    /**
     * 父部门。
     *
     * @return BelongsTo<Department, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * 部门下的管理员（admins.department_id，无外键约束，纯平行字段关联）。
     *
     * @return HasMany<Admin, $this>
     */
    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class, 'department_id');
    }
}
