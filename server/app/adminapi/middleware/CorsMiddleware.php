<?php
declare(strict_types=1);

namespace app\adminapi\middleware;

use think\Request;
use think\Response;

class CorsMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        $origin = $request->header('Origin', '*');

        // OPTIONS 预检请求直接返回
        if ($request->method(true) === 'OPTIONS') {
            return response('', 204)
                ->header([
                    'Access-Control-Allow-Origin'      => $origin,
                    'Access-Control-Allow-Methods'      => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
                    'Access-Control-Allow-Headers'      => 'Authorization, Content-Type, X-Requested-With, Accept, Origin, X-Trace-Id, think-lang',
                    'Access-Control-Allow-Credentials'  => 'true',
                    'Access-Control-Max-Age'            => '86400',
                ]);
        }

        /** @var Response $response */
        $response = $next($request);

        $response->header([
            'Access-Control-Allow-Origin'      => $origin,
            'Access-Control-Allow-Methods'      => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
            'Access-Control-Allow-Headers'      => 'Authorization, Content-Type, X-Requested-With, Accept, Origin, X-Trace-Id, think-lang',
            'Access-Control-Allow-Credentials'  => 'true',
        ]);

        return $response;
    }
}
