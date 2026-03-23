<?php
// 业务逻辑错误 - 中文

return [
    // 菜单
    'menu_name_exists'          => '菜单名称已存在',
    'route_path_exists'         => '路由路径已存在',
    'parent_menu_not_found'     => '父级菜单不存在',
    'button_no_children'        => '按钮类型菜单不能有子菜单',
    'menu_not_found'            => '菜单不存在',
    'parent_not_self'           => '不能设置自己为父级菜单',
    'parent_not_child'          => '不能设置子菜单为父级菜单',
    'menu_has_children'         => '该菜单下还有子菜单，不能删除',
    'menu_used_by_role'         => '该菜单已被角色使用，不能删除',
    'sort_field_missing'        => '排序项缺少必要字段',
    'sort_field_type_error'     => '排序字段类型不正确',
    'sort_parent_mismatch'      => '存在不属于同一父级的菜单，禁止排序',

    // 角色
    'role_code_exists'          => '角色标识已存在',
    'role_not_found'            => '角色不存在',
    'system_role_no_modify'     => '系统角色不能修改标识',
    'system_role_no_delete'     => '系统角色不能删除',
    'role_has_admins'           => '该角色下还有管理员，不能删除',
    'system_role_no_status'     => '系统角色不允许修改状态',

    // 部门
    'dept_code_exists'          => '部门编码已存在',
    'parent_dept_not_found'     => '上级部门不存在',
    'dept_not_found'            => '部门不存在',
    'dept_parent_not_self'      => '上级部门不能是自己',
    'dept_parent_not_child'     => '上级部门不能是当前部门的子部门',
    'dept_has_children'         => '该部门下存在子部门，无法删除',
    'dept_has_admins'           => '该部门下存在管理员，无法删除',

    // 字典
    'dict_not_found'            => '字典不存在',
    'dict_code_exists'          => '字典编码已存在',
    'dict_item_not_found'       => '字典项不存在',
    'dict_item_value_exists'    => '字典项值已存在',

    // 权限
    'permission_code_exists'    => '权限标识已存在',
    'permission_not_found'      => '权限不存在',
    'permission_used_by_role'   => '该权限已被角色使用，不能删除',
    'no_permission_to_create'   => '没有可创建的权限',

    // 文件
    'file_not_found'            => '文件不存在',
    'please_select_file'        => '请选择文件',
    'please_select_files'       => '请选择要删除的文件',
    'file_name_required'        => '文件名不能为空',
    'please_input_group'        => '请输入分组名称',
    'upload_image_only'         => '只允许上传图片文件（jpg、png、gif、webp）',
    'file_size_2mb'             => '文件大小不能超过2MB',
    'file_size_10mb'            => '文件大小不能超过10MB',
    'upload_failed'             => '文件上传失败',
    'please_select_upload'      => '请选择要上传的文件',

    // 通知
    'notification_not_found'    => '通知不存在',

    // 定时任务
    'task_not_found'            => '任务不存在',

    // 配置
    'config_not_found'          => '配置不存在',
    'config_value_required'     => '配置值不能为空',

    // 消息模板
    'template_code_exists'      => '模板标识已存在',
    'template_not_found'        => '模板不存在',
    'template_disabled'         => '消息模板 [%s] 不存在或已禁用',
    'template_code_required'    => '模板标识不能为空',

    // 支付
    'order_no_required'         => '缺少订单号',
    'refund_amount_positive'    => '退款金额必须大于0',
    'payment_order_not_found'   => '支付订单不存在',
    'order_status_no_refund'    => '该订单状态不允许退款',
    'refund_exceed_amount'      => '退款金额不能超过订单金额',

    // 微信
    'miniapp_login_failed'      => '小程序登录失败',
    'get_phone_failed'          => '获取手机号失败',
    'send_subscribe_failed'     => '发送订阅消息失败',
    'generate_qrcode_failed'    => '生成小程序码失败',
    'wechat_api_error'          => '微信接口错误',
    'reply_exists_follow'       => '已存在启用的关注回复',
    'reply_exists_default'      => '已存在启用的默认回复',
    'auto_reply_not_found'      => '自动回复不存在',
    'menu_content_required'     => '菜单内容不能为空',
    'openid_template_required'  => 'openid 和 template_id 不能为空',
    'openid_required'           => '缺少 openid 参数',

    // 用户
    'user_not_found'            => '用户不存在',
    'user_account_disabled'     => '账号已被禁用',
    'user_password_error'       => '密码错误',
    'no_updatable_fields'       => '没有可更新的字段',
    'user_old_password_error'   => '旧密码错误',

    // 代码生成
    'please_specify_table'      => '请指定数据表',
    'please_fill_config'        => '请填写完整配置',
    'record_not_found'          => '记录不存在',

    // 短信
    'aliyun_sms_config_incomplete'  => '阿里云短信配置不完整',
    'tencent_sms_config_incomplete' => '腾讯云短信配置不完整',
    'send_failed'                   => '发送失败',

    // 上传
    'upload_failed_reason'      => '上传失败：%s',

    // 登录
    'login_failed_reason'       => '登录失败: %s',

    // 仪表板
    'get_stats_failed'          => '获取统计数据失败: %s',
    'get_login_log_failed'      => '获取登录日志失败: %s',

    // 字典
    'dict_code_required'        => '字典编码不能为空',

    // 菜单
    'menu_sort_failed_log'      => '菜单批量排序失败',

    // 通用选择提示
    'please_select_admin'       => '请选择要删除的管理员',
    'please_select_role'        => '请选择要删除的角色',
    'please_select_menu'        => '请选择要删除的菜单',
    'please_select_dict'        => '请选择要删除的字典',
    'sort_data_required'        => '排序数据不能为空',
    'permission_data_required'  => '权限数据不能为空',

    // 支付驱动
    'alipay_not_enabled'                    => '支付宝支付未启用',
    'wechat_pay_not_enabled'                => '微信支付未启用',
    'alipay_config_incomplete'              => '支付宝配置不完整，请在系统配置中完善支付设置',
    'alipay_create_order_failed'            => '支付宝创建订单失败',
    'alipay_query_order_failed'             => '支付宝查询订单失败',
    'alipay_refund_failed'                  => '支付宝退款失败',
    'alipay_callback_verify_failed'         => '支付宝回调验签失败',
    'alipay_callback_process_failed'        => '支付宝回调处理失败',
    'wechat_pay_config_incomplete'          => '微信支付配置不完整，请在系统配置中完善支付设置',
    'wechat_pay_key_not_found'              => '微信支付私钥文件不存在',
    'wechat_pay_cert_not_found'             => '微信平台证书文件不存在',
    'wechat_pay_create_order_failed'        => '微信支付创建订单失败',
    'wechat_pay_query_order_failed'         => '微信支付查询订单失败',
    'wechat_pay_refund_failed'              => '微信支付退款失败',
    'callback_data_empty'                   => '回调数据为空',
    'callback_data_decrypt_error'           => '回调数据解密后格式异常',
    'wechat_pay_callback_verify_failed'     => '微信支付回调验证失败',
    'wechat_callback_missing_signature'     => '微信回调缺少签名头信息',
    'wechat_callback_timestamp_expired'     => '微信回调时间戳已过期',
    'wechat_callback_sign_failed'           => '微信回调签名验证失败',

    // 图片处理
    'image_open_failed'         => '图片打开失败',
    'image_save_failed'         => '图片保存失败',
    'thumbnail_failed'          => '缩略图生成失败',
    'image_compress_failed'     => '图片压缩失败',

    // 存储驱动
    'tencent_cos_config_incomplete' => '腾讯云COS配置不完整，请在系统配置中完善存储设置',
    'tencent_cos_upload_failed'     => '腾讯云COS上传失败',
    'tencent_cos_delete_failed'     => '腾讯云COS删除失败',
    'invalid_file'                  => '无效的文件',
    'file_type_not_allowed'         => '不允许的文件类型',
    'file_size_exceeded'            => '文件大小超出限制',
    'aliyun_oss_config_incomplete'  => '阿里云OSS配置不完整，请在系统配置中完善存储设置',
    'aliyun_oss_upload_failed'      => '阿里云OSS上传失败',
    'aliyun_oss_delete_failed'      => '阿里云OSS删除失败',
    'aliyun_oss_url_failed'         => '获取OSS文件URL失败',
    'qiniu_config_incomplete'       => '七牛云配置不完整，请在系统配置中完善存储设置',
    'qiniu_upload_failed'           => '七牛云上传失败',
    'qiniu_delete_failed'           => '七牛云删除失败',
    'local_read_failed'             => '读取本地文件失败',
    'local_write_failed'            => '本地存储写入失败',

    // 微信管理
    'wechat_official_config_incomplete' => '微信公众号配置不完整，请在系统配置中完善微信设置',
    'wechat_miniapp_config_incomplete'  => '微信小程序配置不完整，请在系统配置中完善微信设置',

    // API端
    'invalid_mobile_format'         => '请输入正确的手机号',
    'sms_rate_limit'                => '发送过于频繁，请稍后再试',
    'mobile_password_required'      => '请输入手机号和密码',
    'mobile_code_required'          => '请输入手机号和验证码',
    'register_fields_required'      => '手机号、密码和验证码不能为空',
    'mobile_already_registered'     => '该手机号已注册',
    'missing_code_param'        => '缺少code参数',
    'password_min_length'       => '新密码至少6位',
    'select_payment_channel'    => '请选择支付渠道',
    'invalid_payment_amount'    => '支付金额无效',
    'order_title_required'      => '请填写订单标题',
    'params_incomplete'         => '参数不完整',
    'user_refund_reason'        => '用户申请退款',
    'missing_redirect_url'      => '缺少 redirect_url 参数',
    'missing_code'              => '缺少 code 参数',
];
