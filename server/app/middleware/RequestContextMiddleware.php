<?php

declare(strict_types=1);

namespace app\middleware;

use core\context\RequestContext;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/** 全局中间件（config/middleware.php），先于业务种下 trace，后续中间件与控制器的日志都能带上它。 */
class RequestContextMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $inbound = $request->header('x-trace-id');
        $trace = RequestContext::initTrace(is_string($inbound) ? $inbound : null);

        /** @var Response $response */
        $response = $handler($request);
        $response->withHeader('X-Trace-Id', $trace);

        return $response;
    }
}
