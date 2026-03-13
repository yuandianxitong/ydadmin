<?php
declare(strict_types=1);

namespace app\adminapi\validate\v1\system;

use core\base\Validate;

class DictionaryValidate extends Validate
{
    protected $rule = [
        'name'        => 'require|length:1,100',
        'code'        => 'require|length:1,100|alphaDash',
        'description' => 'max:500',
        'status'      => 'integer|in:0,1',
        'sort'        => 'integer|>=:0',
    ];

    protected $message = [
        'name.require'    => 'validation.dict_name_require',
        'name.length'     => 'validation.dict_name_length',
        'code.require'    => 'validation.dict_code_require',
        'code.length'     => 'validation.dict_code_length',
        'code.alphaDash'  => 'validation.dict_code_alpha_dash',
        'description.max' => 'validation.description_max',
        'status.in'       => 'validation.status_invalid',
        'sort.integer'    => 'validation.sort_integer',
        'sort.>='         => 'validation.sort_min',
    ];

    protected $scene = [
        'create' => ['name', 'code', 'description', 'status', 'sort'],
        'update' => ['name', 'code', 'description', 'status', 'sort'],
    ];
}
