<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\validate\v1\system;

use core\base\Validate;

class MenuValidate extends Validate
{
    protected $rule = [
        'parent_id' => 'integer|>=:0',
        'type' => 'require|integer|in:1,2,3',
        'title' => 'require|length:1,100',
        'name' => 'length:1,100',
        'path' => 'length:1,200',
        'component' => 'length:1,255',
        'redirect' => 'length:1,200',
        'icon' => 'length:1,100',
        'permission' => 'length:1,100',
        'is_hidden' => 'boolean',
        'is_cache' => 'boolean',
        'is_affix' => 'boolean',
        'is_iframe' => 'boolean',
        'external_link' => 'url',
        'breadcrumb' => 'boolean',
        'active_menu' => 'length:1,200',
        'status' => 'integer|in:0,1',
        'sort' => 'integer|>=:0',
    ];

    protected $message = [
        'parent_id.integer' => 'validation.parent_id_integer',
        'parent_id.>=' => 'validation.parent_id_min',
        'type.require' => 'validation.menu_type_require',
        'type.in' => 'validation.menu_type_invalid',
        'title.require' => 'validation.menu_title_require',
        'title.length' => 'validation.menu_title_length',
        'name.length' => 'validation.route_name_length',
        'path.length' => 'validation.route_path_length',
        'component.length' => 'validation.component_length',
        'redirect.length' => 'validation.redirect_length',
        'icon.length' => 'validation.icon_length',
        'permission.length' => 'validation.permission_length',
        'is_hidden.boolean' => 'validation.is_hidden_boolean',
        'is_cache.boolean' => 'validation.is_cache_boolean',
        'is_affix.boolean' => 'validation.is_affix_boolean',
        'is_iframe.boolean' => 'validation.is_iframe_boolean',
        'external_link.url' => 'validation.external_link_url',
        'breadcrumb.boolean' => 'validation.breadcrumb_boolean',
        'active_menu.length' => 'validation.active_menu_length',
        'status.in' => 'validation.status_invalid',
        'sort.integer' => 'validation.sort_integer',
        'sort.>=' => 'validation.sort_min',
    ];

    protected $scene = [
        'create' => [
            'parent_id', 'type', 'title', 'name', 'path', 'component', 'redirect',
            'icon', 'permission', 'is_hidden', 'is_cache', 'is_affix', 'is_iframe',
            'external_link', 'breadcrumb', 'active_menu', 'status', 'sort'
        ],
        'update' => [
            'parent_id', 'type', 'title', 'name', 'path', 'component', 'redirect',
            'icon', 'permission', 'is_hidden', 'is_cache', 'is_affix', 'is_iframe',
            'external_link', 'breadcrumb', 'active_menu', 'status', 'sort'
        ],
    ];

    /**
     * 自定义验证规则
     */
    protected function checkMenuRule($value, $rule, $data = [], $field = '', $title = '')
    {
        // 菜单类型为2（菜单）时，name、path、component不能为空
        if ($data['type'] == 2) {
            if (empty($data['name'])) {
                return lang('validation.menu_name_require');
            }
            if (empty($data['path'])) {
                return lang('validation.menu_path_require');
            }
            if (empty($data['component'])) {
                return lang('validation.menu_component_require');
            }
        }

        // 菜单类型为3（按钮）时，permission不能为空
        if ($data['type'] == 3) {
            if (empty($data['permission'])) {
                return lang('validation.button_permission_require');
            }
        }

        return true;
    }
}
