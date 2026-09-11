<?php

declare(strict_types=1);

namespace tests\Unit\Middleware;

use app\middleware\AdminPermissionMiddleware;
use core\permission\DenyAllChecker;
use core\permission\Permission;
use core\permission\PermissionCheckerInterface;
use core\permission\PermissionSkip;
use core\response\Api;
use support\Container;
use tests\TestCase;
use Webman\Http\Request;

final class FakeChecker implements PermissionCheckerInterface
{
    /** @param list<string> $granted */
    public function __construct(private readonly bool $super = false, private readonly array $granted = [])
    {
    }

    public function isSuperAdmin(int $adminId): bool
    {
        return $this->super;
    }

    public function check(int $adminId, string $permission): bool
    {
        return in_array($permission, $this->granted, true);
    }
}

final class DemoPermController
{
    #[Permission('demo.item.list')]
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

final class AdminPermissionMiddlewareTest extends TestCase
{
    private function code(PermissionCheckerInterface $checker, string $action, int $userId = 1, string $controller = DemoPermController::class): int
    {
        $request = new Request("GET /adminapi/demo HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->controller = $controller;
        $request->action = $action;
        if ($userId > 0) {
            $request->userId = $userId;
        }
        $response = (new AdminPermissionMiddleware($checker))->process($request, fn () => Api::success());

        return (int) json_decode((string) $response->rawBody(), true)['code'];
    }

    public function test_unauthenticated_request_is_401(): void
    {
        $this->assertSame(401, $this->code(new FakeChecker(true), 'annotated', 0));
    }

    public function test_unknown_controller_or_action_is_403(): void
    {
        $this->assertSame(403, $this->code(new FakeChecker(true), 'nope'));
        $this->assertSame(403, $this->code(new FakeChecker(true), 'index', 1, 'app\\NoSuchController'));
    }

    public function test_permission_skip_passes_even_with_deny_all(): void
    {
        $this->assertSame(200, $this->code(new DenyAllChecker(), 'skipped'));
    }

    public function test_annotated_action_follows_checker(): void
    {
        $this->assertSame(200, $this->code(new FakeChecker(false, ['demo.item.list']), 'annotated'));
        $this->assertSame(403, $this->code(new FakeChecker(false, ['other.perm']), 'annotated'));
    }

    public function test_bare_action_is_denied_by_default_except_super_admin(): void
    {
        $this->assertSame(403, $this->code(new FakeChecker(false, ['demo.item.list']), 'bare'));
        $this->assertSame(200, $this->code(new FakeChecker(true), 'bare'));
    }

    public function test_super_admin_passes_annotated_action(): void
    {
        $this->assertSame(200, $this->code(new FakeChecker(true), 'annotated'));
    }

    public function test_container_default_checker_is_deny_all(): void
    {
        $this->assertInstanceOf(DenyAllChecker::class, Container::get(PermissionCheckerInterface::class));
    }
}
