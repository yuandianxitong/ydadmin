<?php
declare(strict_types=1);

namespace app\model\system;

use core\base\Model;

class Department extends Model
{
    protected $name = 'departments';

    protected $fillable = [
        'parent_id', 'name', 'code', 'leader', 'phone', 'email',
        'status', 'sort', 'remark', 'created_by', 'updated_by'
    ];

    protected $type = [
        'parent_id' => 'integer',
        'status'    => 'integer',
        'sort'      => 'integer',
    ];

    protected $append = ['status_text'];

    /**
     * 子部门
     */
    public function children(): \think\model\relation\HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    /**
     * 父部门
     */
    public function parent(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    /**
     * 部门下的管理员
     */
    public function admins(): \think\model\relation\HasMany
    {
        return $this->hasMany(Admin::class, 'department_id', 'id');
    }

    public function getStatusTextAttr($value, $data): string
    {
        return $this->getStatusText((int) ($data['status'] ?? 0), [1 => '正常', 0 => '禁用']);
    }
}
