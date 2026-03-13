<?php
declare(strict_types=1);

namespace app\adminapi\validate\v1\system;

use core\base\Validate;

class NotificationValidate extends Validate
{
    protected $rule = [
        'title'       => 'require|max:200',
        'content'     => 'require',
        'type'        => 'require|in:1,2,3',
        'target_type' => 'in:1,2',
        'status'      => 'in:0,1',
    ];

    protected $message = [
        'title.require'   => 'validation.notification_title_require',
        'title.max'       => 'validation.notification_title_max',
        'content.require' => 'validation.notification_content_require',
        'type.require'    => 'validation.notification_type_require',
        'type.in'         => 'validation.notification_type_invalid',
    ];

    protected $scene = [
        'create' => ['title', 'content', 'type', 'target_type', 'status'],
        'update' => ['title', 'content', 'type', 'target_type', 'status'],
    ];
}
