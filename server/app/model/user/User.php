<?php

declare(strict_types=1);

namespace app\model\user;

use core\base\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 会员（users 表，spec §2.1）。软删；`$hidden` 兜底 password——Repository::find() 经 toArray() 落地，
 * 缺了它 profile/info/管理端 detail 会把口令哈希吐给调用方（红线 Test20）。
 * 不受数据权限约束（spec §8，理由见 app\repository\user\UserRepository 类注释）。
 */
class User extends Model
{
    use SoftDeletes;

    protected $table = 'users';

    /** @var list<string> */
    protected $hidden = ['password', 'token_version'];

    /** @var array<string, string> */
    protected $casts = [
        'gender'      => 'integer',
        'login_count' => 'integer',
        'status'      => 'integer',
        'points'      => 'integer',
        'balance'     => 'decimal:2',
    ];
}
