<?php
declare(strict_types=1);

namespace app\adminapi\validate\v1\system;

use core\base\Validate;

class DepartmentValidate extends Validate
{
    protected $rule = [
        'parent_id' => 'require|integer|>=:0',
        'name'      => 'require|max:100',
        'code'      => 'max:50',
        'leader'    => 'max:50',
        'phone'     => 'max:20',
        'email'     => 'email|max:100',
        'status'    => 'in:0,1',
        'sort'      => 'integer|>=:0',
        'remark'    => 'max:255',
    ];

    protected $message = [
        'parent_id.require' => 'validation.parent_id_require',
        'parent_id.integer' => 'validation.parent_id_integer',
        'name.require'      => 'validation.dept_name_require',
        'name.max'          => 'validation.dept_name_max',
        'code.max'          => 'validation.dept_code_max',
        'email.email'       => 'validation.email_format',
    ];

    protected $scene = [
        'create' => ['parent_id', 'name', 'code', 'leader', 'phone', 'email', 'status', 'sort', 'remark'],
        'update' => ['name', 'code', 'leader', 'phone', 'email', 'status', 'sort', 'remark'],
    ];
}
