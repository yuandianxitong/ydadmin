<?php
declare(strict_types=1);

namespace app\adminapi\validate\v1\system;

use core\base\Validate;

class DictionaryItemValidate extends Validate
{
    protected $rule = [
        'dictionary_id' => 'require|integer|>:0',
        'label'         => 'require|length:1,100',
        'value'         => 'require|length:1,100',
        'tag_type'      => 'max:50',
        'description'   => 'max:500',
        'status'        => 'integer|in:0,1',
        'sort'          => 'integer|>=:0',
    ];

    protected $message = [
        'dictionary_id.require' => 'validation.dict_id_require',
        'dictionary_id.integer' => 'validation.dict_id_integer',
        'label.require'         => 'validation.label_require',
        'label.length'          => 'validation.label_length',
        'value.require'         => 'validation.value_require',
        'value.length'          => 'validation.value_length',
        'tag_type.max'          => 'validation.tag_type_max',
        'description.max'       => 'validation.description_max',
        'status.in'             => 'validation.status_invalid',
        'sort.integer'          => 'validation.sort_integer',
        'sort.>='               => 'validation.sort_min',
    ];

    protected $scene = [
        'create' => ['dictionary_id', 'label', 'value', 'tag_type', 'description', 'status', 'sort'],
        'update' => ['label', 'value', 'tag_type', 'description', 'status', 'sort'],
    ];
}
