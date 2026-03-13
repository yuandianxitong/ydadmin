<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */

declare(strict_types=1);

namespace core\http;

use think\Request as ThinkRequest;

class Request extends ThinkRequest
{
    /**
     * 获取用户ID
     */
    public function getUserId(): int
    {
        return (int)$this->userId ?? 0;
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo(): array
    {
        return $this->userInfo ?? [];
    }

    /**
     * 获取客户端IP
     */
    public function getClientIp(): string
    {
        return $this->ip();
    }

    /**
     * 获取用户代理
     */
    public function getUserAgent(): string
    {
        return $this->header('User-Agent', '');
    }

    /**
     * 是否是AJAX请求
     */
    public function isAjax(): bool
    {
        return $this->isAjax() || $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * 是否是API请求
     */
    public function isApi(): bool
    {
        return strpos($this->pathinfo(), 'api/') === 0;
    }

    /**
     * 是否是管理端请求
     */
    public function isAdmin(): bool
    {
        return strpos($this->pathinfo(), 'adminapi/') === 0;
    }

    /**
     * 获取分页参数
     */
    public function getPaginateParams(): array
    {
        return [
            'page' => max(1, (int)$this->param('page', 1)),
            'limit' => min(100, max(1, (int)$this->param('limit', 15)))
        ];
    }

    /**
     * 获取排序参数
     */
    public function getSortParams(): array
    {
        $sort = $this->param('sort', 'id');
        $order = $this->param('order', 'desc');

        return [
            'sort' => $sort,
            'order' => in_array(strtolower($order), ['asc', 'desc']) ? $order : 'desc'
        ];
    }

    /**
     * 获取搜索参数
     */
    public function getSearchParams(): array
    {
        return array_filter([
            'keyword' => $this->param('keyword', ''),
            'status' => $this->param('status'),
            'category_id' => $this->param('category_id'),
            'start_time' => $this->param('start_time'),
            'end_time' => $this->param('end_time'),
        ], function ($value) {
            return $value !== '' && $value !== null;
        });
    }
}
