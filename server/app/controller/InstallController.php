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

        return (new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], (string) file_get_contents(resource_path() . '/views/install/index.html')))
            ->cookie(self::TOKEN_COOKIE, $token, null, '/install', '', false, false, 'Strict');
    }

    /**
     * 写操作前的跨站防护：令牌对得上，且（带了 Origin/Referer 时）来源与本站同源。
     * 不依赖 session / Redis——这两样在装完之前都还没配好。
     */
    private function assertSameSiteRequest(Request $request): void
    {
        $host = (string) $request->host();
        foreach (['origin', 'referer'] as $header) {
            $value = (string) $request->header($header, '');
            if ($value === '') {
                continue;
            }
            $origin = parse_url($value, PHP_URL_HOST);
            $port = parse_url($value, PHP_URL_PORT);
            $originHost = is_string($origin) ? $origin . ($port === null ? '' : ':' . $port) : '';
            if ($originHost !== $host && $originHost !== (string) parse_url('//' . $host, PHP_URL_HOST)) {
                throw new ForbiddenException(lang('install.cross_site_rejected'));
            }
        }

        $cookie = (string) $request->cookie(self::TOKEN_COOKIE, '');
        $header = (string) $request->header('x-install-token', '');
        if ($cookie === '' || $header === '' || !hash_equals($cookie, $header)) {
            throw new ForbiddenException(lang('install.cross_site_rejected'));
        }
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
        if ($data['import_demo'] === true) {
            $proto = strtolower((string) $request->header('x-forwarded-proto', ''));
            $scheme = $proto === 'https' || $proto === 'http'
                ? $proto
                : (((string) $request->header('https', '')) === 'on' ? 'https' : 'http');
            $host = (string) $request->host();
            $data['site_url'] = Installer::normalizeSiteUrl($host === '' ? '' : $scheme . '://' . $host);
        }
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
