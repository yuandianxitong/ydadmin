<?php
declare(strict_types=1);

namespace app\model\user;

use core\base\Model;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'nickname', 'avatar', 'mobile', 'email', 'password',
        'gender', 'birthday', 'openid', 'unionid', 'mini_openid',
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

    /**
     * 根据手机号查找
     */
    public static function findByMobile(string $mobile): ?static
    {
        return static::where('mobile', $mobile)->find();
    }

    /**
     * 根据 openid 查找
     */
    public static function findByOpenid(string $openid): ?static
    {
        return static::where('openid', $openid)->find();
    }

    /**
     * 根据小程序 openid 查找
     */
    public static function findByMiniOpenid(string $openid): ?static
    {
        return static::where('mini_openid', $openid)->find();
    }
}
