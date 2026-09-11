<?php

declare(strict_types=1);

namespace tests\RedLine;

use core\auth\TokenVersion;
use support\Db;
use tests\Support\ApiTestCase;

/** 红线：禁用、删除、重置/修改密码后，该管理员已签发的 token 立即失效（spec §4.4）。 */
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
}
