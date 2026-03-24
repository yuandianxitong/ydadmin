<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
namespace app;

use core\exception\ApiException;
use core\exception\AuthException;
use core\exception\BusinessException;
use core\exception\PermissionException;
use core\exception\ValidationException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 全局异常处理
 *
 * 对 API 请求统一返回 JSON 格式的错误响应，
 * 避免 ThinkPHP 默认的 HTML 错误页面返回给前端。
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
        AuthException::class,
        PermissionException::class,
        BusinessException::class,
        ValidationException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     */
    public function report(Throwable $exception): void
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): Response
    {
        // 仅对 API 请求（JSON / adminapi / api 路由）返回统一 JSON
        if ($this->isApiRequest($request)) {
            return $this->renderApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * 判断是否为 API 请求
     */
    protected function isApiRequest($request): bool
    {
        // 路径检测（pathinfo 或完整 URL）
        $pathInfo = $request->pathinfo();
        $url = $request->url();
        if (str_starts_with($pathInfo, 'adminapi/') || str_starts_with($pathInfo, 'api/')
            || str_contains($url, '/adminapi/') || str_contains($url, '/api/')) {
            return true;
        }

        // Content-Type / Accept 含 json
        $contentType = $request->header('content-type', '');
        $accept = $request->header('accept', '');
        if (str_contains($contentType, 'json') || str_contains($accept, 'json')) {
            return true;
        }

        return $request->isJson() || $request->isAjax();
    }

    /**
     * 统一 API 异常 JSON 响应
     */
    protected function renderApiException($request, Throwable $e): Response
    {
        // ---- 自定义业务异常 ----

        if ($e instanceof ValidationException) {
            return $this->jsonResponse(422, $e->getFirstError(), ['errors' => $e->getErrors()]);
        }

        if ($e instanceof AuthException) {
            return $this->jsonResponse(401, $e->getMessage());
        }

        if ($e instanceof PermissionException) {
            return $this->jsonResponse(403, $e->getMessage());
        }

        if ($e instanceof BusinessException) {
            return $this->jsonResponse($e->getCode() ?: 400, $e->getMessage(), $e->getData());
        }

        if ($e instanceof ApiException) {
            return $this->jsonResponse(
                $e->getCode() ?: 500,
                $e->getMessage(),
                $e->getData(),
                $e->getHttpCode()
            );
        }

        // ---- ThinkPHP 内置异常 ----

        if ($e instanceof ValidateException) {
            return $this->jsonResponse(422, $e->getMessage());
        }

        if ($e instanceof ModelNotFoundException || $e instanceof DataNotFoundException) {
            return $this->jsonResponse(404, lang('messages.data_not_found'));
        }

        if ($e instanceof HttpException) {
            return $this->jsonResponse(
                $e->getStatusCode(),
                $this->getHttpMessage($e->getStatusCode()),
                [],
                $e->getStatusCode()
            );
        }

        // ---- 未知异常 ----

        // 生产环境隐藏详细错误信息，避免泄露敏感数据
        $isDebug = app()->isDebug();
        $message = $isDebug ? $e->getMessage() : lang('messages.server_error');
        $data = $isDebug ? [
            'exception' => get_class($e),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => array_slice(explode("\n", $e->getTraceAsString()), 0, 10),
        ] : [];

        return $this->jsonResponse(500, $message, $data, 500);
    }

    /**
     * 构建统一格式的 JSON 响应
     */
    protected function jsonResponse(int $code, string $message, $data = [], int $httpStatus = 200): Response
    {
        // 对于客户端错误（4xx），HTTP 状态码保持 200，由 code 字段区分业务错误
        // 对于服务端错误（5xx），HTTP 状态码跟随 code
        if ($httpStatus === 200 && $code >= 500) {
            $httpStatus = $code;
        }

        return json([
            'code'      => $code,
            'message'   => $message,
            'data'      => $data ?: null,
            'timestamp' => time(),
        ])->code($httpStatus);
    }

    /**
     * HTTP 状态码对应的默认提示
     */
    protected function getHttpMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => lang('messages.bad_request'),
            401 => lang('messages.unauthorized'),
            403 => lang('messages.forbidden'),
            404 => lang('messages.not_found'),
            405 => lang('messages.method_not_allowed'),
            429 => lang('messages.too_many_requests'),
            500 => lang('messages.server_error'),
            502 => lang('messages.bad_gateway'),
            503 => lang('messages.service_unavailable'),
            default => lang('messages.request_failed'),
        };
    }
}
