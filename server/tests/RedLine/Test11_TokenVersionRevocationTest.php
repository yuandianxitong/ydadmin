<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\service\system\AdminService;
use core\auth\TokenVersion;
use support\Container;
use support\Db;
use support\Redis;
use tests\Support\ApiTestCase;

/** 红线：禁用、删除、重置/修改密码后，该管理员已签发的 token 立即失效（spec §4.4）；Redis 丢了版本号时 fail closed。 */
final class Test11_TokenVersionRevocationTest extends ApiTestCase
{
    private const BASE = '/adminapi/system/admin';

    public function test_disabling_revokes_tokens_and_re_enabling_does_not_revive_them(): void
    {
        $super = $this->actingAsAdmin('super');
        $target = $this->actingAsAdmin();

        $this->put(self::BASE . "/{$target->id}/status", ['status' => 0], $super->token)->assertOk();
        $this->get('/adminapi/auth/info', [], $target->token)->assertCode(401);
        $this->put(self::BASE . "/{$target->id}/status", ['status' => 1], $super->token)->assertOk();
        $this->get('/adminapi/auth/info', [], $target->token)->assertCode(401);
        $this->login($target->username, $target->password)->assertOk();
    }

    public function test_password_reset_and_change_revoke_tokens(): void
    {
        $super = $this->actingAsAdmin('super');
        $target = $this->actingAsAdmin();
        $this->put(self::BASE . "/{$target->id}/reset-password", ['password' => 'Reset#2026'], $super->token)->assertOk();
        $this->get('/adminapi/auth/info', [], $target->token)->assertCode(401);

        $self = $this->actingAsAdmin();
        $this->put(self::BASE . '/change-password', ['old_password' => $self->password, 'new_password' => 'Mine#2026'], $self->token)->assertOk();
        $this->get('/adminapi/auth/info', [], $self->token)->assertCode(401);
    }

    public function test_deleting_revokes_tokens(): void
    {
        $super = $this->actingAsAdmin('super');
        $target = $this->actingAsAdmin();

        $this->delete(self::BASE . "/{$target->id}", [], $super->token)->assertOk();
        $this->get('/adminapi/auth/info', [], $target->token)->assertCode(401);
    }

    public function test_refreshed_token_carries_the_current_version(): void
    {
        $target = $this->actingAsAdmin();
        TokenVersion::bump($target->id);
        $this->get('/adminapi/auth/info', [], $target->token)->assertCode(401);

        $fresh = $this->login($target->username, $target->password)->assertOk()->data()['token'];
        $refreshed = $this->post('/adminapi/auth/refresh', [], $fresh)->assertOk()->data()['token'];
        $this->get('/adminapi/auth/info', [], $refreshed)->assertOk();
    }

    public function test_role_changes_do_not_revoke_tokens(): void
    {
        $super = $this->actingAsAdmin('super');
        $member = $this->actingAsAdmin(['system.admin.list']);
        $roleId = (int) Db::table('admin_roles')->where('admin_id', $member->id)->value('role_id');

        $this->put("/adminapi/system/role/{$roleId}/assign-permissions", ['menu_ids' => [20]], $super->token)->assertOk();
        $this->get('/adminapi/auth/info', [], $member->token)->assertOk(); // 只清权限缓存，不自增版本号
    }

    /** Redis 丢了版本号 key（淘汰、FLUSHDB、未持久化就重启）：重新播种出另一个随机基数，旧 token 一律失效。 */
    public function test_losing_the_version_key_fails_closed(): void
    {
        $target = $this->actingAsAdmin();
        $this->get('/adminapi/auth/info', [], $target->token)->assertOk();

        Redis::del("admin_token_ver:{$target->id}");
        $this->get('/adminapi/auth/info', [], $target->token)->assertCode(401);

        $fresh = $this->login($target->username, $target->password)->assertOk()->data()['token'];
        $this->get('/adminapi/auth/info', [], $fresh)->assertOk();
    }

    /**
     * AdminService::forgetAdmin() 先吊销、再清缓存：清缓存抛异常时吊销不能被跳过。
     * 把 AdminService 单例的 dataScopeResolver 属性置为未初始化（一访问就抛 Error）来模拟清缓存失败，用例结束还原。
     */
    public function test_revocation_is_not_skipped_when_a_cache_clear_fails(): void
    {
        $super = $this->actingAsAdmin('super');
        $target = $this->actingAsAdmin();
        $service = Container::get(AdminService::class);
        $resolver = (fn () => $this->dataScopeResolver)->call($service);
        (function (): void {
            unset($this->dataScopeResolver);
        })->call($service);

        try {
            // afterCommit 回调抛出：数据已提交，异常冒到 Handler，响应 500
            $this->assertSame(500, $this->put(self::BASE . "/{$target->id}/status", ['status' => 0], $super->token)->status());
        } finally {
            (function () use ($resolver): void {
                $this->dataScopeResolver = $resolver;
            })->call($service);
        }

        $this->assertSame(0, (int) Db::table('admins')->where('id', $target->id)->value('status'), '禁用已提交');
        $this->get('/adminapi/auth/info', [], $target->token)->assertCode(401);
    }
}
