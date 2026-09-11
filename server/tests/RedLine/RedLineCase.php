<?php

declare(strict_types=1);

namespace tests\RedLine;

use core\auth\TokenManager;
use core\permission\Permission;
use core\permission\PermissionCheckerInterface;
use core\permission\PermissionSkip;
use tests\TestCase;
use Webman\Http\Request;
use Webman\Http\Response;

/** 红线夹具：定义在基类文件里，保证任一红线测试单独运行时都已加载。 */
final class RlController
{
    #[Permission('rl.item.list')]
    public function annotated(): string
    {
        return 'ok';
    }

    #[PermissionSkip]
    public function skipped(): string
    {
        return 'ok';
    }

    public function bare(): string
    {
        return 'ok';
    }
}

/** 按管理员 ID 授权的检查器：superAdmins 里的是超管，grants 是 adminId => 权限点列表。 */
final class RlChecker implements PermissionCheckerInterface
{
    /**
     * @param list<int> $superAdmins
     * @param array<int, list<string>> $grants
     */
    public function __construct(private readonly array $superAdmins = [], private readonly array $grants = [])
    {
    }

    public function isSuperAdmin(int $adminId): bool
    {
        return in_array($adminId, $this->superAdmins, true);
    }

    public function check(int $adminId, string $permission): bool
    {
        return in_array($permission, $this->grants[$adminId] ?? [], true);
    }
}

abstract class RedLineCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TokenManager::flushInstances();
    }

    protected function adminToken(int $adminId = 1): string
    {
        return TokenManager::scope('admin')->generate(['admin_id' => $adminId, 'username' => "rl-{$adminId}"]);
    }

    protected function userToken(int $userId = 1): string
    {
        return TokenManager::scope('user')->generate(['user_id' => $userId]);
    }

    protected function request(string $path, ?string $token = null): Request
    {
        $auth = $token !== null ? "Authorization: Bearer {$token}\r\n" : '';

        return new Request("GET {$path} HTTP/1.1\r\nHost: localhost\r\n{$auth}\r\n");
    }

    protected function code(Response $response): int
    {
        return (int) json_decode((string) $response->rawBody(), true)['code'];
    }
}
