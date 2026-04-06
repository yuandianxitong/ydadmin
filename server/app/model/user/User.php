<?php
declare(strict_types=1);

namespace app\model\user;

use core\base\Model;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'nickname', 'avatar', 'mobile', 'email', 'password',
        'gender', 'birthday', 'openid', 'oa_openid', 'unionid', 'mini_openid',
        'last_login_ip', 'last_login_time', 'login_count', 'status',
    ];

    protected $hidden = ['password'];

    protected $type = [
        'gender'      => 'integer',
        'login_count' => 'integer',
        'status'      => 'integer',
        'balance'     => 'float',
        'points'      => 'integer',
    ];
}
