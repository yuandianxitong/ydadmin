<?php

declare(strict_types=1);

namespace tests\Feature\System;

use core\datascope\DataScope;
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

    public function test_non_super_cannot_grant_the_system_role_via_update(): void
    {
        $actor = $this->actingAsAdmin(['system.admin.update']);
        $target = $this->actingAsAdmin();

        $this->assertSame(lang('business.system_role_no_assign'), $this->put(self::BASE . "/{$target->id}", ['role_ids' => [1]], $actor->token)->assertCode(400)->message());
    }

    /** 数据范围逃逸：本部门范围的管理员不能把人（含自己）挪到范围外的部门，否则下一请求就能看到那个部门的管理员。 */
    public function test_dept_scoped_admin_cannot_assign_a_department_outside_the_scope(): void
    {
        $a = $this->createDepartment(['name' => '范围A']);
        $b = $this->createDepartment(['name' => '范围B']);
        $actor = $this->actingAsAdmin(['system.admin.create', 'system.admin.update'], ['department_id' => $a], ['data_scope' => DataScope::DEPT]);
        $inScope = $this->actingAsAdmin([], ['department_id' => $a]);

        $this->assertSame(lang('business.dept_out_of_scope'), $this->post(self::BASE, $this->payload(['department_id' => $b]), $actor->token)->assertCode(400)->message());
        $this->assertSame(lang('business.dept_out_of_scope'), $this->put(self::BASE . "/{$inScope->id}", ['department_id' => $b], $actor->token)->assertCode(400)->message());
        $this->assertSame(lang('business.cannot_change_own_department'), $this->put(self::BASE . "/{$actor->id}", ['department_id' => $b], $actor->token)->assertCode(400)->message());
        $this->assertSame($a, (int) Db::table('admins')->where('id', $inScope->id)->value('department_id'));
        $this->assertSame($a, (int) Db::table('admins')->where('id', $actor->id)->value('department_id'));

        // 范围内的部门照常可分配；原样回传自己的部门（前端表单总会带上 department_id）不算修改
        $this->trackAdmin((int) $this->post(self::BASE, $this->payload(['department_id' => $a]), $actor->token)->assertOk()->data()['id']);
        $this->put(self::BASE . "/{$actor->id}", ['nickname' => '改自己昵称', 'department_id' => $a], $actor->token)->assertOk();
    }

    /** 仅本人范围（deptIds 为空）：任何非空部门都在范围外，但原样回传自己当前的部门不受影响。 */
    public function test_self_scoped_admin_can_resubmit_own_department_but_not_assign_any(): void
    {
        $own = $this->createDepartment();
        $actor = $this->actingAsAdmin(['system.admin.create', 'system.admin.update'], ['department_id' => $own], ['data_scope' => DataScope::SELF]);

        $this->assertSame(lang('business.dept_out_of_scope'), $this->post(self::BASE, $this->payload(['department_id' => $own]), $actor->token)->assertCode(400)->message());
        $this->put(self::BASE . "/{$actor->id}", ['nickname' => '改自己昵称', 'department_id' => $own], $actor->token)->assertOk();
    }

    public function test_super_admin_can_assign_any_department(): void
    {
        $super = $this->actingAsAdmin('super');
        $b = $this->createDepartment();
        $target = $this->actingAsAdmin([], ['department_id' => $this->createDepartment()]);

        $id = (int) $this->post(self::BASE, $this->payload(['department_id' => $b]), $super->token)->assertOk()->data()['id'];
        $this->trackAdmin($id);
        $this->put(self::BASE . "/{$target->id}", ['department_id' => $b], $super->token)->assertOk();
        $this->put(self::BASE . "/{$super->id}", ['department_id' => $b], $super->token)->assertOk();

        foreach ([$id, $target->id, $super->id] as $adminId) {
            $this->assertSame($b, (int) Db::table('admins')->where('id', $adminId)->value('department_id'));
        }
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

    public function test_update_rejects_blank_username_email_or_status(): void
    {
        $super = $this->actingAsAdmin('super');
        $target = $this->actingAsAdmin();
        $originalUsername = $target->username;
        $originalEmail = (string) Db::table('admins')->where('id', $target->id)->value('email');

        $usernameResponse = $this->put(self::BASE . "/{$target->id}", ['username' => ''], $super->token);
        $usernameResponse->assertCode(422);
        $this->assertArrayHasKey('username', $usernameResponse->data()['errors']);

        $emailResponse = $this->put(self::BASE . "/{$target->id}", ['email' => ''], $super->token);
        $emailResponse->assertCode(422);
        $this->assertArrayHasKey('email', $emailResponse->data()['errors']);

        $statusResponse = $this->put(self::BASE . "/{$target->id}", ['status' => ''], $super->token);
        $statusResponse->assertCode(422);
        $this->assertArrayHasKey('status', $statusResponse->data()['errors']);

        $this->assertSame($originalUsername, Db::table('admins')->where('id', $target->id)->value('username'));
        $this->assertSame($originalEmail, Db::table('admins')->where('id', $target->id)->value('email'));
    }

    public function test_store_rejects_blank_status(): void
    {
        $super = $this->actingAsAdmin('super');

        $response = $this->post(self::BASE, $this->payload(['status' => '']), $super->token);
        $this->trackAdmin((int) ($response->data()['id'] ?? 0)); // 回归时误建的行也要清掉
        $this->assertArrayHasKey('status', $response->assertCode(422)->data()['errors']);
    }

    public function test_update_rejects_null_role_ids_and_keeps_roles(): void
    {
        $super = $this->actingAsAdmin('super');
        $target = $this->actingAsAdmin(['system.admin.list']);
        $roleId = $this->roleOf($target);

        $response = $this->put(self::BASE . "/{$target->id}", ['role_ids' => null], $super->token);
        $this->assertArrayHasKey('role_ids', $response->assertCode(422)->data()['errors']);
        $this->assertSame([$roleId], array_map('intval', Db::table('admin_roles')->where('admin_id', $target->id)->pluck('role_id')->all()));
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

        $this->assertSame(['count' => 2], $this->post(self::BASE . '/batch-delete', ['ids' => [$a->id, $b->id]], $actor->token)->assertOk()->data());

        $c = $this->actingAsAdmin();
        // super 建在 $c 之后（id 更大）：visibleIds() 按 id 升序处理，保证 $c 先被处理、super 保护规则最后才失败，
        // 这样断言才真正覆盖了「$c 的软删除与 token 吊销随事务一起回滚」。
        $super = $this->actingAsAdmin('super');
        $this->post(self::BASE . '/batch-delete', ['ids' => [$c->id, $super->id]], $actor->token)->assertCode(400);
        $this->assertNull(Db::table('admins')->where('id', $c->id)->value('deleted_at'), '任一失败整体回滚');
        $this->get('/adminapi/auth/info', [], $c->token)->assertOk(); // $c 的 token 吊销回调也应随事务被丢弃
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

    public function test_update_with_status_zero_applies_disable_protections(): void
    {
        $actor = $this->actingAsAdmin(['system.admin.update']);
        $super = $this->actingAsAdmin('super');
        $target = $this->actingAsAdmin();

        // updateAdmin() 先跑 assertCanModify()——非超管经 update() 触碰超管账号的任何字段（含 status）都会先在这里
        // 被挡下，拿到的是更笼统的 super_admin_no_modify，不会走到 assertCanDisable() 的 super_admin_no_disable；
        // 这与既有的 test_non_super_cannot_modify_a_super_admin 一致，是比「仅挡禁用」更严格的保护。
        $this->assertSame(lang('auth.super_admin_no_modify'), $this->put(self::BASE . "/{$super->id}", ['status' => 0], $actor->token)->assertCode(400)->message());
        $this->assertSame(lang('auth.cannot_disable_self'), $this->put(self::BASE . "/{$actor->id}", ['status' => 0], $actor->token)->assertCode(400)->message());
        $this->put(self::BASE . "/{$target->id}", ['status' => 0], $actor->token)->assertOk();
        $this->get('/adminapi/auth/info', [], $target->token)->assertCode(401);
    }

    /**
     * assertCanModify() 只在「actor 非超管」时才会拦；两个超管互相操作时它直接放行，
     * 禁用超管的唯一防线是 updateAdmin() 里的 `if ($disabling) { $this->assertCanDisable($id); }`。
     * 这条用例专门钉住这道防线，防止它被静默删掉。
     */
    public function test_super_admin_cannot_disable_another_super_admin_via_update(): void
    {
        $first = $this->actingAsAdmin('super');
        $second = $this->actingAsAdmin('super');

        $this->assertSame(lang('auth.super_admin_no_disable'), $this->put(self::BASE . "/{$second->id}", ['status' => 0], $first->token)->assertCode(400)->message());

        $this->assertSame(1, (int) Db::table('admins')->where('id', $second->id)->value('status'));
        $this->get('/adminapi/auth/info', [], $second->token)->assertOk();
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
