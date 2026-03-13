<?php
declare(strict_types=1);

namespace app\adminapi\validate\v1\system;

use core\base\Validate;

class CronJobValidate extends Validate
{
    protected $rule = [
        'name'        => 'require|max:100',
        'command'     => 'require|max:255',
        'expression'  => 'require|max:100',
        'description' => 'max:255',
        'status'      => 'in:0,1',
        'sort'        => 'integer|>=:0',
    ];

    protected $message = [
        'name.require'       => 'validation.task_name_require',
        'name.max'           => 'validation.task_name_max',
        'command.require'    => 'validation.command_require',
        'command.max'        => 'validation.command_max',
        'expression.require' => 'validation.expression_require',
        'expression.max'     => 'validation.expression_max',
    ];

    protected $scene = [
        'create' => ['name', 'command', 'expression', 'description', 'status', 'sort'],
        'update' => ['name', 'command', 'expression', 'description', 'status', 'sort'],
    ];
}
