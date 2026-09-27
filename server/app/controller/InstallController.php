<?php

declare(strict_types=1);

namespace app\controller;

use core\base\Controller;
use core\exception\ForbiddenException;
use core\install\EnvironmentChecker;
use core\install\Installer;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

class InstallController extends Controller
{
    #[Inject]
    protected Installer $installer;

    #[Inject]
    protected EnvironmentChecker $checker;

    /** 双提交令牌的 cookie 名；向导页下发，写操作要求请求头带同一个值。 */
    public const TOKEN_COOKIE = 'yd_install_token';

    #[\core\permission\PermissionSkip]
    public function index(): Response
    {
        if ($this->installer->isInstalled()) {
            $message = htmlspecialchars(lang('install.already_installed'), ENT_QUOTES, 'UTF-8');

            return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><title>Install</title></head><body><p>' . $message . '</p></body></html>');
        }

        // 装完之前这页未登录可达，所以写操作要防跨站：这里发一个随机令牌，
        // 向导的 JS 把它回传到请求头。跨站页面既读不到这个 cookie，也加不了自定义头。
        $token = bin2hex(random_bytes(16));
        $html = str_replace(
            '__INSTALL_TOKEN__',
            $token,
            (string) file_get_contents(resource_path() . '/views/install/index.html'),
        );

        return (new Response(200, [
            'Content-Type'  => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
        ], $html))->cookie(self::TOKEN_COOKIE, $token, null, '/install', '', false, false, 'Strict');
    }

    /**
     * 写操作前的跨站防护：令牌对得上，且（带了 Origin/Referer 时）来源与本站同源。
     * 不依赖 session / Redis——这两样在装完之前都还没配好。
     * 主机名必须一致；两边都写了端口时端口也要一致。只有一边带端口则放行。
     * 反代把 Host 换成上游 IP 时，浏览器 Origin 仍是公网域名，这种也放行。
     */
    private function assertSameSiteRequest(Request $request): void
    {
        $host = strtolower(trim((string) $request->host()));
        foreach (['origin', 'referer'] as $header) {
            $value = trim((string) $request->header($header, ''));
            if ($value === '' || strcasecmp($value, 'null') === 0) {
                continue;
            }
            if (!$this->sameHost($host, $value)) {
                throw new ForbiddenException(lang('install.cross_site_rejected'));
            }
        }

        $cookie = (string) $request->cookie(self::TOKEN_COOKIE, '');
        $header = (string) $request->header('x-install-token', '');
        if ($cookie === '' || $header === '' || !hash_equals($cookie, $header)) {
            throw new ForbiddenException(lang('install.cross_site_rejected'));
        }
    }

    private function sameHost(string $hostHeader, string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host']) || !is_string($parts['host'])) {
            return false;
        }
        [$headerHost, $headerPort] = $this->splitHostPort($hostHeader);
        $originName = $this->bareHost((string) $parts['host']);
        $headerName = $this->bareHost($headerHost);
        $originPort = isset($parts['port']) ? (int) $parts['port'] : null;
        if ($originName === $headerName) {
            return $originPort === null || $headerPort === null || $originPort === $headerPort;
        }

        // nginx 默认把 Host 设成 proxy_pass 的上游（127.0.0.1:8000 或 webman），
        // 浏览器 Origin 仍是用户打开的域名。
        return $this->isUpstreamHost($headerName) && str_contains($originName, '.');
    }

    private function isUpstreamHost(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP) !== false || !str_contains($host, '.');
    }

    /** @return array{0: string, 1: int|null} */
    private function splitHostPort(string $host): array
    {
        if (str_starts_with($host, '[')) {
            $end = strpos($host, ']');
            if ($end === false) {
                return [trim($host, '[]'), null];
            }
            $name = substr($host, 1, $end - 1);
            $port = null;
            if (isset($host[$end + 1]) && $host[$end + 1] === ':') {
                $parsed = (int) substr($host, $end + 2);
                $port = $parsed > 0 ? $parsed : null;
            }

            return [strtolower($name), $port];
        }
        if (preg_match('/^(.+):(\d+)$/', $host, $match) === 1) {
            return [strtolower($match[1]), (int) $match[2]];
        }

        return [strtolower($host), null];
    }

    private function bareHost(string $host): string
    {
        return strtolower(trim($host, '[]'));
    }

    /**
     * 安装时的对外地址。Host 是上游 IP 或单标签名时，用浏览器 Origin / Referer。
     * 请求体里的 site_url 不采用。
     */
    private function resolveSiteUrl(Request $request): string
    {
        $hostHeader = strtolower(trim((string) $request->host()));
        [$headerHost, $headerPort] = $this->splitHostPort($hostHeader);
        $headerName = $this->bareHost($headerHost);
        $browser = $this->browserSiteUrl($request);
        if ($browser !== null && ($headerName === '' || $this->isUpstreamHost($headerName))) {
            return Installer::normalizeSiteUrl($browser);
        }

        $proto = strtolower((string) $request->header('x-forwarded-proto', ''));
        $scheme = $proto === 'https' || $proto === 'http'
            ? $proto
            : (((string) $request->header('https', '')) === 'on' ? 'https' : 'http');
        if ($headerName === '') {
            return 'http://localhost';
        }
        $withPort = $headerName;
        if ($headerPort !== null && !($scheme === 'http' && $headerPort === 80) && !($scheme === 'https' && $headerPort === 443)) {
            $withPort .= ':' . $headerPort;
        }

        return Installer::normalizeSiteUrl($scheme . '://' . $withPort);
    }

    private function browserSiteUrl(Request $request): ?string
    {
        foreach (['origin', 'referer'] as $header) {
            $value = trim((string) $request->header($header, ''));
            if ($value === '' || strcasecmp($value, 'null') === 0) {
                continue;
            }
            $parts = parse_url($value);
            if (!is_array($parts) || !isset($parts['host']) || !is_string($parts['host'])) {
                continue;
            }
            $name = $this->bareHost($parts['host']);
            if ($name === '' || $this->isUpstreamHost($name)) {
                continue;
            }
            $scheme = strtolower((string) ($parts['scheme'] ?? 'http')) === 'https' ? 'https' : 'http';
            $port = isset($parts['port']) ? (int) $parts['port'] : null;
            $suffix = '';
            if ($port !== null && $port > 0 && !($scheme === 'http' && $port === 80) && !($scheme === 'https' && $port === 443)) {
                $suffix = ':' . $port;
            }

            return $scheme . '://' . $name . $suffix;
        }

        return null;
    }

    #[\core\permission\PermissionSkip]
    public function environment(): Response
    {
        if ($this->installer->isInstalled()) {
            return $this->error(lang('install.already_installed'), 400);
        }

        return $this->success([
            'checks'   => $this->checker->check(),
            'defaults' => $this->connectionDefaults(),
        ], lang('messages.get_success'));
    }

    #[\core\permission\PermissionSkip]
    public function testConnection(Request $request): Response
    {
        if ($this->installer->isInstalled()) {
            return $this->error(lang('install.already_installed'), 400);
        }
        $this->assertSameSiteRequest($request);

        $data = $this->validate((array) $request->all(), $this->testConnectionRules());
        $this->installer->testDatabase($data);
        $this->installer->testRedis($data);

        return $this->success();
    }

    #[\core\permission\PermissionSkip]
    public function run(Request $request): Response
    {
        if ($this->installer->isInstalled()) {
            return $this->error(lang('install.already_installed'), 400);
        }
        $this->assertSameSiteRequest($request);

        $data = $this->validate((array) $request->all(), $this->runRules());
        $data['import_demo'] = Installer::wantsDemo($data['import_demo'] ?? null);
        // 反代常把 Host 换成 127.0.0.1。浏览器 Origin 才是安装时打开的域名，网站地址和演示封面都用它。
        $data['site_url'] = $this->resolveSiteUrl($request);
        $this->installer->run($data);

        return $this->success(['restart' => true], lang('install.restart_hint'));
    }

    /** @return array<string, string> */
    private function testConnectionRules(): array
    {
        return $this->connectionRules() + [
            'username' => 'nullable|string',
            'password' => 'nullable|string',
        ];
    }

    /** @return array<string, string> */
    private function runRules(): array
    {
        return $this->connectionRules() + [
            'username'    => 'required|string|min:3|max:20|alpha_dash:ascii',
            'password'    => 'required|string|min:6|max:20',
            'email'       => 'nullable|email|max:100',
            'nickname'    => 'nullable|string|max:50',
            'import_demo' => 'nullable',
        ];
    }

    /** @return array<string, int|string> */
    private function connectionDefaults(): array
    {
        $mysql = (array) config('database.connections.mysql');
        $redis = (array) config('redis.default');

        return [
            'db_host'    => (string) ($mysql['host'] ?? '127.0.0.1'),
            'db_port'    => (int) ($mysql['port'] ?? 3306),
            'db_name'    => (string) ($mysql['database'] ?? ''),
            // 不回 db_user：装完之前这条接口未登录可达，库用户名没必要送给任何访客
            'redis_host' => (string) ($redis['host'] ?? '127.0.0.1'),
            'redis_port' => (int) ($redis['port'] ?? 6379),
            'redis_db'   => (int) ($redis['database'] ?? 0),
        ];
    }

    /** @return array<string, string> */
    private function connectionRules(): array
    {
        return [
            'db_host'        => 'required|string',
            'db_port'        => 'required|integer',
            'db_name'        => 'required|regex:/^[A-Za-z0-9_]+$/',
            'db_user'        => 'required|string',
            'db_password'    => 'nullable|string',
            'redis_host'     => 'required|string',
            'redis_port'     => 'required|integer',
            'redis_password' => 'nullable|string',
            'redis_db'       => 'required|integer',
        ];
    }
}
