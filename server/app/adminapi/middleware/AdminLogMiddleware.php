<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\middleware;

use core\http\Middleware;
use Closure;
use think\Request;
use think\Response;

class AdminLogMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 3);

        // 记录操作日志
        $this->recordOperationLog($request, $response, $executionTime);

        return $response;
    }

    /**
     * 记录操作日志
     */
    protected function recordOperationLog(Request $request, Response $response, float $executionTime): void
    {
        // 只记录写操作
        $method = strtoupper($request->method());
        if (!in_array($method, ['POST', 'PUT', 'DELETE'])) {
            return;
        }

        $userId = $request->userId ?? 0;
        $username = $request->username ?? '';

        if (!$userId) {
            return;
        }

        // 获取操作描述
        $action = $this->getActionDescription($request);

        // 记录日志（异步队列写入）
        \core\queue\QueueManager::push(\app\job\AdminOperationLogJob::class, [
            'admin_id' => $userId,
            'username' => $username,
            'method' => $method,
            'path' => $request->pathinfo(),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent', ''),
            'action' => $action['action'],
            'description' => $action['description'],
            'params' => $request->param(),
            'result' => [
                'code' => $response->getCode(),
                'message' => $this->getResponseMessage($response)
            ],
            'execution_time' => $executionTime,
        ]);
    }

    /**
     * 获取操作描述
     */
    protected function getActionDescription(Request $request): array
    {
        $path = $request->pathinfo();
        $method = strtoupper($request->method());

        $actions = [
            'POST /adminapi/system/admin' => ['action' => lang('messages.log_create_admin'), 'description' => lang('messages.log_create_admin_desc')],
            'PUT /adminapi/system/admin' => ['action' => lang('messages.log_update_admin'), 'description' => lang('messages.log_update_admin_desc')],
            'DELETE /adminapi/system/admin' => ['action' => lang('messages.log_delete_admin'), 'description' => lang('messages.log_delete_admin_desc')],
            'POST /adminapi/system/role' => ['action' => lang('messages.log_create_role'), 'description' => lang('messages.log_create_role_desc')],
            'PUT /adminapi/system/role' => ['action' => lang('messages.log_update_role'), 'description' => lang('messages.log_update_role_desc')],
            'DELETE /adminapi/system/role' => ['action' => lang('messages.log_delete_role'), 'description' => lang('messages.log_delete_role_desc')],
            'POST /adminapi/system/permission' => ['action' => lang('messages.log_create_perm'), 'description' => lang('messages.log_create_perm_desc')],
            'PUT /adminapi/system/permission' => ['action' => lang('messages.log_update_perm'), 'description' => lang('messages.log_update_perm_desc')],
            'DELETE /adminapi/system/permission' => ['action' => lang('messages.log_delete_perm'), 'description' => lang('messages.log_delete_perm_desc')],
            'POST /adminapi/system/menu' => ['action' => lang('messages.log_create_menu'), 'description' => lang('messages.log_create_menu_desc')],
            'PUT /adminapi/system/menu' => ['action' => lang('messages.log_update_menu'), 'description' => lang('messages.log_update_menu_desc')],
            'DELETE /adminapi/system/menu' => ['action' => lang('messages.log_delete_menu'), 'description' => lang('messages.log_delete_menu_desc')],
        ];

        $key = $method . ' ' . $path;
        return $actions[$key] ?? ['action' => lang('messages.operation'), 'description' => lang('messages.execute_operation')];
    }

    /**
     * 获取响应消息
     */
    protected function getResponseMessage(Response $response): string
    {
        $content = $response->getContent();

        if (is_string($content)) {
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['message'])) {
                return $data['message'];
            }
        }

        return '';
    }
}
