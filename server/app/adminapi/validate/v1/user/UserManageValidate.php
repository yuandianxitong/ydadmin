<?php
declare(strict_types=1);
namespace app\adminapi\validate\v1\user;

use core\base\Validate;

class UserManageValidate extends Validate
{
    protected $rule = [
        'user_id' => 'require|integer|>:0',
        'amount'  => 'require|number|between:-99999,99999',
        'points'  => 'require|integer|between:-999999,999999',
        'remark'  => 'max:200',
        'status'  => 'require|in:0,1',
    ];

    protected $message = [
        'user_id.require' => '请选择用户',
        'amount.require'  => '请输入金额',
        'amount.number'   => '金额格式不正确',
        'points.require'  => '请输入积分',
        'points.integer'  => '积分必须为整数',
        'remark.max'      => '备注最多200个字符',
    ];

    protected $scene = [
        'adjustBalance' => ['user_id', 'amount', 'remark'],
        'adjustPoints'  => ['user_id', 'points', 'remark'],
        'status'        => ['status'],
    ];
}
