<?php

declare(strict_types=1);

namespace app\controller;

use support\Response;
use Webman\Http\Request;

/**
 * 前端 SPA 托管：静态资源由 webman 直接返回（先于路由），这里只处理前端路由路径的回退。
 */
final class SpaController
{
    private const MOBILE_UA = '/Mobile|Android|iPhone|iPad|iPod|MicroMessenger/i';

    /** 站点根：按 UA 跳转到移动端或 PC 端（沿用 TP8 版 app/index 行为）。 */
    public function home(Request $request): Response
    {
        $ua = (string) $request->header('user-agent', '');

        return redirect(preg_match(self::MOBILE_UA, $ua) === 1 ? '/mobile/' : '/pc/');
    }

    public function admin(Request $request, string $path = ''): Response
    {
        return $this->serve('admin');
    }

    public function pc(Request $request, string $path = ''): Response
    {
        return $this->serve('pc');
    }

    public function mobile(Request $request, string $path = ''): Response
    {
        return $this->serve('mobile');
    }

    private function serve(string $app): Response
    {
        $index = public_path() . DIRECTORY_SEPARATOR . $app . DIRECTORY_SEPARATOR . 'index.html';
        if (!is_file($index)) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=utf-8'], ucfirst($app) . ' site not deployed');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], (string) file_get_contents($index));
    }
}
