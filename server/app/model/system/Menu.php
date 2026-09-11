<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 菜单（menus 表）。
 *
 * 真权限源：type=3（按钮）行的 permission 字段，经 role_menus + admin_roles 关联到管理员。
 * type: 1=目录 2=菜单 3=按钮。
 */
class Menu extends Model
{
    use SoftDeletes;

    protected $table = 'menus';

    /** @var array<string, string> */
    protected $casts = [
        'parent_id'  => 'integer',
        'type'       => 'integer',
        'is_hidden'  => 'boolean',
        'is_cache'   => 'boolean',
        'is_affix'   => 'boolean',
        'is_iframe'  => 'boolean',
        'breadcrumb' => 'boolean',
        'status'     => 'integer',
        'sort'       => 'integer',
        'meta'       => 'array',
    ];

    /**
     * 关联角色：role_menus 复合主键(role_id,menu_id)，无 id 列。
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_menus', 'menu_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * 子菜单（按 sort/id 升序）。
     *
     * 注：orderBy() 经 Eloquent\Builder::__call 转发给底层 Query\Builder 后固定 `return $this;`
     * （运行时依旧是本 HasMany 实例），但 phpstan 的 @mixin 静态推断会把链式调用的返回类型
     * 误判成 Query\Builder——拆成独立语句，只 `return` 变量本身，避免这个纯静态分析层面的
     * 类型丢失（不是加 cast/@var 掩盖，是运行时真实语义本就如此）。
     *
     * @return HasMany<Menu, $this>
     */
    public function children(): HasMany
    {
        $relation = $this->hasMany(self::class, 'parent_id');
        $relation->orderBy('sort')->orderBy('id');

        return $relation;
    }

    /**
     * 父菜单
     *
     * @return BelongsTo<Menu, $this>
     */
    public function parentMenu(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
