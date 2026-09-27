<?php

declare(strict_types=1);

namespace app\middleware;

use core\install\Installer;
use core\response\Api;
use support\Container;
use support\Response;
use Webman\Http\Request;
use Webman\Http\Response as HttpResponse;
use Webman\MiddlewareInterface;

/**
 * 未安装守卫：config/install.lock 不存在，且库与 .env 都表明还没装过时，
 * /install* 放行；/adminapi、/api 或 Accept JSON → HTTP 503 + data.installed=false；其余 302 /install/。
 * 不在实例上缓存 isInstalled()：中间件是容器单例。
 */
class InstallGuardMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): HttpResponse
    {
        $path = $request->path();
        if (str_starts_with($path, '/install')) {
            return $handler($request);
        }

        /** @var Installer $installer */
        $installer = Container::get(Installer::class);
        if ($installer->isInstalled()) {
            return $handler($request);
        }

        $accept = strtolower((string) $request->header('accept', ''));
        $wantsJson = str_starts_with($path, '/adminapi')
            || str_starts_with($path, '/api')
            || str_contains($accept, 'application/json');

        if ($wantsJson) {
            return Api::errorWithStatus(lang('install.not_installed'), 503, ['installed' => false]);
        }

        return new Response(302, ['Location' => '/install/']);
    }
}
