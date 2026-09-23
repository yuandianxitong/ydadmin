<?php

declare(strict_types=1);

namespace app\controller;

use core\base\Controller;
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

    #[\core\permission\PermissionSkip]
    public function index(): Response
    {
        if ($this->installer->isInstalled()) {
            $message = htmlspecialchars(lang('install.already_installed'), ENT_QUOTES, 'UTF-8');

            return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><title>Install</title></head><body><p>' . $message . '</p></body></html>');
        }

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], (string) file_get_contents(resource_path() . '/views/install/index.html'));
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

        $data = $this->validate((array) $request->all(), $this->runRules());
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
            'username' => 'required|string|min:3|max:20|alpha_dash:ascii',
            'password' => 'required|string|min:6|max:20',
            'email'    => 'nullable|email|max:100',
            'nickname' => 'nullable|string|max:50',
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
            'db_user'    => (string) ($mysql['username'] ?? ''),
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
