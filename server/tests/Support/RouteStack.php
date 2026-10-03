<?php

declare(strict_types=1);

namespace tests\Support;

use Webman\Middleware;
use Webman\Route\Route as RouteObject;

final class RouteStack
{
    /** @return list<string> 从外到内的中间件类名 */
    public static function outerToInner(RouteObject $route): array
    {
        $callback = $route->getCallback();
        $stack = Middleware::getMiddleware('', '', $callback, $route);
        $names = [];
        foreach ($stack as $item) {
            $names[] = is_array($item) ? (string) $item[0] : (string) $item;
        }

        return array_reverse($names);
    }
}
