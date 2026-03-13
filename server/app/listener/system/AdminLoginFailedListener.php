<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\listener\system;

use app\model\system\AdminLoginLog;

/**
 * 管理员登录失败监听器
 *
 * 事件数据：
 * - admin_id: int|null  管理员ID（用户名不存在时为0）
 * - username: string    用户名
 * - ip: string          登录IP
 * - user_agent: string  浏览器UA
 * - message: string     失败原因
 */
class AdminLoginFailedListener
{
    public function handle(array $event): void
    {
        AdminLoginLog::record([
            'admin_id'      => $event['admin_id'] ?? 0,
            'username'       => $event['username'],
            'ip'             => $event['ip'],
            'user_agent'     => $event['user_agent'],
            'login_result'   => false,
            'login_message'  => $event['message'],
        ]);
    }
}
