<?php

declare(strict_types=1);

namespace tests\Feature\System;

use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\TestAdmin;

final class AdminApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/system/admin';

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        $suffix = bin2hex(random_bytes(3));

        return array_merge(['username' => "api_{$suffix}", 'email' => "api_{$suffix}@test.local", 'password' => 'Secret#123', 'nickname' => '接口测试'], $overrides);
    }

    private function roleOf(TestAdmin $admin): int
    {
        return (int) Db::table('admin_roles')->where('admin_id', $admin->id)->value('role_id');
    }

    public function test_index_returns_pagination_with_roles_and_caps_limit(): void
    {
        $super = $this->actingAsAdmin('super');
        $data = $this->get(self::BASE, ['limit' => 500], $super->token)->assertOk()->data();

        $this->assertSame(['list', 'pagination'], array_keys($data));
        $this->assertSame(100, $data['pagination']['per_page']);
        $this->assertArrayHasKey('roles', $data['list'][0]);
        $this->assertArrayNotHasKey('password', $data['list'][0]);
    }

    public function test_index_filters_by_keyword_and_status(): void
    {
        $super = $this->actingAsAdmin('super');
        $target = $this->actingAsAdmin([], ['nickname' => '关键字探针', 'status' => 0]);

        $byKeyword = $this->get(self::BASE, ['keyword' => '关键字探针'], $super->token)->assertOk()->data();
        $this->assertSame([$target->id], array_column($byKeyword['list'], 'id'));
        $byStatus = $this->get(self::BASE, ['keyword' => '关键字探针', 'status' => 1], $super->token)->assertOk()->data();
        $this->assertSame(0, $byStatus['pagination']['total']);
    }

    public function test_missing_permission_is_http_200_with_code_403(): void
    {
        $admin = $this->actingAsAdmin(['system.role.list']);
        $response = $this->get(self::BASE, [], $admin->token);

        $this->assertSame(200, $response->status(), '403 只写在 body.code，HTTP 状态仍是 200');
        $response->assertCode(403);
    }

    public function test_show_returns_admin_info_shape(): void
    {
        $super = $this->actingAsAdmin('super');
        $target = $this->actingAsAdmin(['system.admin.list']);
        $data = $this->get(self::BASE . "/{$target->id}", [], $super->token)->assertOk()->data();

        foreach (['id', 'username', 'status_text', 'roles', 'permissions', 'menu_ids'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
        $this->assertArrayNotHasKey('password', $data);
        $this->assertSame(['system.admin.list'], $data['permissions']);
        $this->get(self::BASE . '/999999999', [], $super->token)->assertCode(404);
    }

    public function test_store_creates_admin_with_roles_department_and_creator(): void
    {
        $super = $this->actingAsAdmin('super');
        $roleId = $this->roleOf($this->actingAsAdmin(['system.admin.list']));

        $response = $this->post(self::BASE, $this->payload(['role_ids' => [$roleId], 'department_id' => 2]), $super->token)->assertOk();
        $id = (int) $response->data()['id'];
        $this->trackAdmin($id);

        $this->assertSame(lang('messages.create_success'), $response->message());
        $this->assertArrayNotHasKey('password', $response->data());
        $this->assertSame([$roleId], array_map('intval', Db::table('admin_roles')->where('admin_id', $id)->pluck('role_id')->all()));
        $row = Db::table('admins')->where('id', $id)->first();
        $this->assertSame($super->id, (int) $row->created_by);
        $this->assertSame(2, (int) $row->department_id);
    }

    public function test_store_rejects_duplicates_including_soft_deleted(): void
    {
        $super = $this->actingAsAdmin('super');
        $existing = $this->actingAsAdmin();
        $gone = $this->actingAsAdmin([], ['deleted_at' => date('Y-m-d H:i:s')]);

        $this->assertSame(lang('auth.username_exists'), $this->post(self::BASE, $this->payload(['username' => $existing->username]), $super->token)->assertCode(400)->message());
        $this->assertSame(lang('auth.username_exists'), $this->post(self::BASE, $this->payload(['username' => $gone->username]), $super->token)->assertCode(400)->message());
        $this->assertSame(lang('auth.email_exists'), $this->post(self::BASE, $this->payload(['email' => "{$existing->username}@test.local"]), $super->token)->assertCode(400)->message());
    }

    public function test_store_validates_password_against_config_and_department(): void
    {
        $super = $this->actingAsAdmin('super');
        $this->setConfig('password_min_length', '10');

        $response = $this->post(self::BASE, $this->payload(['password' => 'Short#12']), $super->token);
        $response->assertCode(422);
        $this->assertArrayHasKey('password', $response->data()['errors']);
        $this->assertSame(lang('business.dept_not_found'), $this->post(self::BASE, $this->payload(['password' => 'LongEnough#1', 'department_id' => 999999]), $super->token)->assertCode(400)->message());
    }

    public function test_non_super_cannot_grant_the_system_role(): void
    {
        $actor = $this->actingAsAdmin(['system.admin.create']);

        $this->assertSame(lang('business.system_role_no_assign'), $this->post(self::BASE, $this->payload(['role_ids' => [1]]), $actor->token)->assertCode(400)->message());
    }

    public function test_update_changes_fields_and_roles_and_revokes_on_password_change(): void
    {
        $super = $this->actingAsAdmin('super');
        $target = $this->actingAsAdmin(['system.admin.list']);

        $this->put(self::BASE . "/{$target->id}", ['nickname' => '新昵称', 'role_ids' => []], $super->token)->assertOk();
        $this->assertSame('新昵称', Db::table('admins')->where('id', $target->id)->value('nickname'));
        $this->get(self::BASE, [], $target->token)->assertCode(403);         // 角色清空，下一请求即失去权限
        $this->get('/adminapi/auth/info', [], $target->token)->assertOk();    // 没改密码，token 仍有效

        $this->put(self::BASE . "/{$target->id}", ['password' => 'Changed#123'], $super->token)->assertOk();
        $this->get('/adminapi/auth/info', [], $target->token)->assertCode(401);
    }

    public function test_non_super_cannot_modify_a_super_admin(): void
    {
        $actor = $this->actingAsAdmin(['system.admin.update']);
        $super = $this->actingAsAdmin('super');

        $this->assertSame(lang('auth.super_admin_no_modify'), $this->put(self::BASE . "/{$super->id}/reset-password", ['password' => 'Takeover#1'], $actor->token)->assertCode(400)->message());
        $this->assertSame(lang('auth.super_admin_no_modify'), $this->put(self::BASE . "/{$super->id}", ['nickname' => '被改'], $actor->token)->assertCode(400)->message());
    }

    public function test_delete_protections_and_revocation(): void
    {
        $actor = $this->actingAsAdmin(['system.admin.delete']);
        $super = $this->actingAsAdmin('super');
        $target = $this->actingAsAdmin();

        $this->assertSame(lang('auth.super_admin_no_delete'), $this->delete(self::BASE . "/{$super->id}", [], $actor->token)->assertCode(400)->message());
        $this->assertSame(lang('auth.cannot_delete_self'), $this->delete(self::BASE . "/{$actor->id}", [], $actor->token)->assertCode(400)->message());
        $this->delete(self::BASE . "/{$target->id}", [], $actor->token)->assertOk();
        $this->assertNotNull(Db::table('admins')->where('id', $target->id)->value('deleted_at'), '软删除');
        $this->get('/adminapi/auth/info', [], $target->token)->assertCode(401);
    }

    public function test_batch_delete_returns_count_and_rolls_back_on_protected_rows(): void
    {
        $actor = $this->actingAsAdmin(['system.admin.delete']);
        $a = $this->actingAsAdmin();
        $b = $this->actingAsAdmin();
        $super = $this->actingAsAdmin('super');

        $this->assertSame(['count' => 2], $this->post(self::BASE . '/batch-delete', ['ids' => [$a->id, $b->id]], $actor->token)->assertOk()->data());

        $c = $this->actingAsAdmin();
        $this->post(self::BASE . '/batch-delete', ['ids' => [$c->id, $super->id]], $actor->token)->assertCode(400);
        $this->assertNull(Db::table('admins')->where('id', $c->id)->value('deleted_at'), '任一失败整体回滚');
        $this->assertSame(lang('business.please_select_admin'), $this->post(self::BASE . '/batch-delete', ['ids' => []], $actor->token)->assertCode(400)->message());
    }

    public function test_status_protections_and_revocation(): void
    {
        $actor = $this->actingAsAdmin(['system.admin.status']);
        $super = $this->actingAsAdmin('super');
        $target = $this->actingAsAdmin();

        $this->assertSame(lang('auth.super_admin_no_disable'), $this->put(self::BASE . "/{$super->id}/status", ['status' => 0], $actor->token)->assertCode(400)->message());
        $this->assertSame(lang('auth.cannot_disable_self'), $this->put(self::BASE . "/{$actor->id}/status", ['status' => 0], $actor->token)->assertCode(400)->message());
        $this->put(self::BASE . "/{$target->id}/status", ['status' => 0], $actor->token)->assertOk();
        $this->get('/adminapi/auth/info', [], $target->token)->assertCode(401);
        $this->put(self::BASE . "/{$target->id}/status", ['status' => 2], $actor->token)->assertCode(422);
    }

    public function test_reset_password(): void
    {
        $actor = $this->actingAsAdmin(['system.admin.update']);
        $target = $this->actingAsAdmin();

        $this->put(self::BASE . "/{$target->id}/reset-password", ['password' => 'Reset#1234'], $actor->token)->assertOk();
        $this->get('/adminapi/auth/info', [], $target->token)->assertCode(401);
        $this->login($target->username, 'Reset#1234')->assertOk();
    }

    public function test_change_own_password(): void
    {
        $admin = $this->actingAsAdmin();

        $this->assertSame(lang('auth.old_password_error'), $this->put(self::BASE . '/change-password', ['old_password' => 'nope', 'new_password' => 'Brand#New1'], $admin->token)->assertCode(400)->message());
        $this->put(self::BASE . '/change-password', ['old_password' => $admin->password, 'new_password' => $admin->password], $admin->token)->assertCode(422);
        $this->put(self::BASE . '/change-password', ['old_password' => $admin->password, 'new_password' => 'Brand#New1'], $admin->token)->assertOk();
        $this->get('/adminapi/auth/info', [], $admin->token)->assertCode(401); // 改自己的密码后当前会话也失效
        $this->login($admin->username, 'Brand#New1')->assertOk();
    }

    public function test_role_options_expose_only_id_name_title(): void
    {
        $admin = $this->actingAsAdmin();
        $options = $this->get(self::BASE . '/role/options', [], $admin->token)->assertOk()->data();

        $this->assertSame(['id', 'name', 'title'], array_keys($options[0]));
    }
}
