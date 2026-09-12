<?php

declare(strict_types=1);

namespace app\exception;

use core\exception\BusinessException;
use core\exception\ValidationException;
use core\response\Api;
use Throwable;
use Webman\Exception\ExceptionHandler;
use Webman\Http\Request;
use Webman\Http\Response;

/** 异常 → 统一响应（spec §7.2）。$this->debug 由 webman 以 config('app.debug') 注入。 */
class Handler extends ExceptionHandler
{
    /** @var array<int, class-string<Throwable>> */
    public $dontReport = [BusinessException::class];

    public function render(Request $request, Throwable $exception): Response
    {
        if ($exception instanceof ValidationException) {
            return Api::error($exception->getMessage(), 422, ['errors' => $exception->errors()]);
        }

        if ($exception instanceof BusinessException) {
            $code = (int) $exception->getCode();
            $code = $code > 0 ? $code : 400;
            return $code >= 500
                ? Api::errorWithStatus($exception->getMessage(), $code)
                : Api::error($exception->getMessage(), $code);
        }

        if (!$this->debug) {
            return Api::errorWithStatus(lang('messages.server_error'), 500);
        }

        return Api::errorWithStatus($exception->getMessage(), 500, [
            'exception' => $exception::class,
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
        ]);
    }
}
