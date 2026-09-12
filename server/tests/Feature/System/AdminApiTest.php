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

    /** @return list<int> 管理员当前的角色 id（升序） */
    private function roleIdsOf(int $adminId): array
    {
        $ids = array_map('intval', Db::table('admin_roles')->where('admin_id', $adminId)->pluck('role_id')->all());
        sort($ids);

        return $ids;
    }

    /**
     * 建一个不挂给任何人的角色，授予 $permissions 对应的种子菜单。
     *
     * @param list<string> $permissions
     * @param list<int> $deptIds 自定义范围的部门
     */
    private function createRole(array $permissions, int $dataScope = DataScope::SELF, array $deptIds = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('roles')->insertGetId([
            'name'       => 'grant_' . bin2hex(random_bytes(3)),
            'title'      => '授权测试角色',
            'data_scope' => $dataScope,
            'is_system'  => 0,
            'status'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->track('roles', $id);
        $menuIds = $permissions === [] ? [] : Db::table('menus')->whereIn('permission', $permissions)->pluck('id')->all();
        $this->assertCount(count($permissions), $menuIds, '种子菜单里找不到部分权限点：' . implode(',', $permissions));
        foreach ($menuIds as $menuId) {
            Db::table('role_menus')->insert(['role_id' => $id, 'menu_id' => (int) $menuId, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ($deptIds as $deptId) {
            Db::table('role_departments')->insert(['role_id' => $id, 'department_id' => $deptId, 'created_at' => $now, 'updated_at' => $now]);
        }

        return $id;
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

    /** 路径 D 与自授：谁都不能改自己的角色——超管移除自己的系统角色会自锁，非超管给自己加角色是提权。原样回传不算修改。 */
    public function test_nobody_can_change_their_own_roles(): void
    {
        $super = $this->actingAsAdmin('super');

        $this->assertSame(lang('business.cannot_change_own_roles'), $this->put(self::BASE . "/{$super->id}", ['role_ids' => []], $super->token)->assertCode(400)->message());
        $this->assertSame([1], $this->roleIdsOf($super->id));
        $this->put(self::BASE . "/{$super->id}", ['nickname' => '超管改昵称', 'role_ids' => [1]], $super->token)->assertOk();
        $this->assertSame('超管改昵称', Db::table('admins')->where('id', $super->id)->value('nickname'));

        $actor = $this->actingAsAdmin(['system.admin.update']);
        $own = $this->roleOf($actor);
        $extra = $this->createRole([]); // 无菜单、仅本人：规则 2、3 都拦不住它，只有规则 1 能拦
        $this->assertSame(lang('business.cannot_change_own_roles'), $this->put(self::BASE . "/{$actor->id}", ['role_ids' => [$own, $extra]], $actor->token)->assertCode(400)->message());
        $this->assertSame([$own], $this->roleIdsOf($actor->id));
        $this->put(self::BASE . "/{$actor->id}", ['nickname' => '改自己昵称', 'role_ids' => [$own, $own]], $actor->token)->assertOk();
    }

    /** 路径 A（经 role_ids）：非超管授予的角色，菜单并集不能超出自己已有的菜单。 */
    public function test_non_super_cannot_assign_roles_beyond_own_permissions(): void
    {
        $actor = $this->actingAsAdmin(['system.admin.create', 'system.admin.update']);
        $target = $this->actingAsAdmin();
        $targetRole = $this->roleOf($target);
        $wider = $this->createRole(['system.admin.create', 'system.role.list']);
        $payload = $this->payload(['role_ids' => [$wider]]);

        $response = $this->post(self::BASE, $payload, $actor->token);
        $this->trackAdmin((int) ($response->data()['id'] ?? 0)); // 回归时误建的行也要清掉
        $this->assertSame(lang('business.role_exceeds_own_permissions'), $response->assertCode(400)->message());
        $this->assertSame(0, Db::table('admins')->where('username', $payload['username'])->count());
        $this->assertSame(lang('business.role_exceeds_own_permissions'), $this->put(self::BASE . "/{$target->id}", ['role_ids' => [$wider]], $actor->token)->assertCode(400)->message());
        $this->assertSame([$targetRole], $this->roleIdsOf($target->id));

        // 子集照常可授；超管不受此限
        $subset = $this->createRole(['system.admin.create']);
        $this->put(self::BASE . "/{$target->id}", ['role_ids' => [$subset]], $actor->token)->assertOk();
        $this->assertSame([$subset], $this->roleIdsOf($target->id));
        $super = $this->actingAsAdmin('super');
        $this->put(self::BASE . "/{$target->id}", ['role_ids' => [$wider]], $super->token)->assertOk();
        $this->assertSame([$wider], $this->roleIdsOf($target->id));
    }

    /** 基于角色的数据范围逃逸：授予的角色按目标的部门模拟出范围，必须被操作者的范围覆盖。 */
    public function test_non_super_cannot_assign_a_role_whose_scope_exceeds_own(): void
    {
        $a = $this->createDepartment(['name' => '授权范围A']);
        $actor = $this->actingAsAdmin(['system.admin.create', 'system.admin.update'], ['department_id' => $a], ['data_scope' => DataScope::DEPT]);
        $inScope = $this->actingAsAdmin([], ['department_id' => $a]);
        $inScopeRole = $this->roleOf($inScope);

        // 「全部」范围的非系统角色
        $allScope = $this->createRole([], DataScope::ALL);
        $payload = $this->payload(['department_id' => $a, 'role_ids' => [$allScope]]);
        $response = $this->post(self::BASE, $payload, $actor->token);
        $this->trackAdmin((int) ($response->data()['id'] ?? 0));
        $this->assertSame(lang('business.role_scope_exceeds_own'), $response->assertCode(400)->message());
        $this->assertSame(0, Db::table('admins')->where('username', $payload['username'])->count());
        $this->assertSame(lang('business.role_scope_exceeds_own'), $this->put(self::BASE . "/{$inScope->id}", ['role_ids' => [$allScope]], $actor->token)->assertCode(400)->message());

        // 「本部门及下级」：部门 A 有下级时，目标的范围会越过操作者的「本部门」
        $this->createDepartment(['name' => '授权范围A1', 'parent_id' => $a]);
        $withChildren = $this->createRole([], DataScope::DEPT_AND_CHILDREN);
        $this->assertSame(lang('business.role_scope_exceeds_own'), $this->put(self::BASE . "/{$inScope->id}", ['role_ids' => [$withChildren]], $actor->token)->assertCode(400)->message());

        // 自定义范围里有操作者范围外的部门
        $custom = $this->createRole([], DataScope::CUSTOM, [$this->createDepartment(['name' => '授权范围B'])]);
        $this->assertSame(lang('business.role_scope_exceeds_own'), $this->put(self::BASE . "/{$inScope->id}", ['role_ids' => [$custom]], $actor->token)->assertCode(400)->message());

        $this->assertSame([$inScopeRole], $this->roleIdsOf($inScope->id));
    }

    /**
     * 防提权判定 fail closed：禁用的角色照样算数。否则非超管可以先挂一个「菜单更宽 / 范围更大」的禁用角色，
     * 等超管哪天把它启用，被挂的人就静默获得了越权。生效的权限与数据范围仍然只认启用的角色，不受影响。
     */
    public function test_disabled_roles_still_count_in_the_anti_escalation_checks(): void
    {
        $dept = $this->createDepartment(['name' => '禁用角色A']);
        $actor = $this->actingAsAdmin(['system.admin.update'], ['department_id' => $dept], ['data_scope' => DataScope::DEPT]);
        $target = $this->actingAsAdmin([], ['department_id' => $dept], ['data_scope' => DataScope::DEPT]);
        $targetRole = $this->roleOf($target);
        $widerMenus = $this->createRole(['system.role.list'], DataScope::DEPT);
        $widerScope = $this->createRole([], DataScope::ALL);
        Db::table('roles')->whereIn('id', [$widerMenus, $widerScope])->update(['status' => 0]);

        $this->assertSame(lang('business.role_exceeds_own_permissions'), $this->put(self::BASE . "/{$target->id}", ['role_ids' => [$widerMenus]], $actor->token)->assertCode(400)->message());
        $this->assertSame(lang('business.role_scope_exceeds_own'), $this->put(self::BASE . "/{$target->id}", ['role_ids' => [$widerScope]], $actor->token)->assertCode(400)->message());
        $this->assertSame([$targetRole], $this->roleIdsOf($target->id));

        // 超管不受这两道判定约束
        $super = $this->actingAsAdmin('super');
        $this->put(self::BASE . "/{$target->id}", ['role_ids' => [$widerScope]], $super->token)->assertOk();
        $this->assertSame([$widerScope], $this->roleIdsOf($target->id));
    }

    /** 换部门也要预演：持「本部门及下级」角色的人换到有下级的部门（该部门本身在范围内），范围会越过操作者。 */
    public function test_moving_an_admin_cannot_widen_their_scope_past_the_actor(): void
    {
        $a = $this->createDepartment(['name' => '换部门A']);
        $b = $this->createDepartment(['name' => '换部门B']);
        $this->createDepartment(['name' => '换部门B1', 'parent_id' => $b]);
        $c = $this->createDepartment(['name' => '换部门C']);
        $actor = $this->actingAsAdmin(['system.admin.update'], [], ['data_scope' => DataScope::CUSTOM, 'dept_ids' => [$a, $b, $c]]);
        $target = $this->actingAsAdmin([], ['department_id' => $a], ['data_scope' => DataScope::DEPT_AND_CHILDREN]);

        $this->assertSame(lang('business.role_scope_exceeds_own'), $this->put(self::BASE . "/{$target->id}", ['department_id' => $b], $actor->token)->assertCode(400)->message());
        $this->assertSame($a, (int) Db::table('admins')->where('id', $target->id)->value('department_id'));

        // 换到没有下级的范围内部门：模拟出的范围只有该部门，放行
        $this->put(self::BASE . "/{$target->id}", ['department_id' => $c], $actor->token)->assertOk();
        $this->assertSame($c, (int) Db::table('admins')->where('id', $target->id)->value('department_id'));
    }

    /** 正对照：本部门范围的管理员，在本部门内授予「菜单是自己子集、范围为本部门」的角色。 */
    public function test_dept_scoped_admin_can_grant_a_subset_role_inside_own_department(): void
    {
        $a = $this->createDepartment(['name' => '授权正例A']);
        $actor = $this->actingAsAdmin(['system.admin.list', 'system.admin.create', 'system.admin.update'], ['department_id' => $a], ['data_scope' => DataScope::DEPT]);
        $role = $this->createRole(['system.admin.list'], DataScope::DEPT);

        $id = (int) $this->post(self::BASE, $this->payload(['department_id' => $a, 'role_ids' => [$role]]), $actor->token)->assertOk()->data()['id'];
        $this->trackAdmin($id);
        $this->assertSame([$role], $this->roleIdsOf($id));
        $this->assertSame($a, (int) Db::table('admins')->where('id', $id)->value('department_id'));

        $inScope = $this->actingAsAdmin([], ['department_id' => $a]);
        $this->put(self::BASE . "/{$inScope->id}", ['role_ids' => [$role]], $actor->token)->assertOk();
        $this->assertSame([$role], $this->roleIdsOf($inScope->id));
    }

    /**
     * 「只能管理不比自己权力大的人」原先只覆盖改角色与换部门：范围内一个持更宽角色的账号只要不是超管，
     * 非超管的委派者重置它的密码就能登进去接管它的全部权限。改密码、禁用、删除前都要按目标「当前」的
     * 角色与部门再过一次防提权判定。
     */
    public function test_scope_limited_actor_cannot_take_over_a_more_powerful_in_scope_admin(): void
    {
        $dept = $this->createDepartment(['name' => '接管范围A']);
        $actor = $this->actingAsAdmin(['system.admin.update', 'system.admin.status', 'system.admin.delete'], ['department_id' => $dept], ['data_scope' => DataScope::DEPT]);
        $victim = $this->actingAsAdmin(['system.role.list'], ['department_id' => $dept], ['data_scope' => DataScope::DEPT]);
        $hash = (string) Db::table('admins')->where('id', $victim->id)->value('password');
        $message = lang('business.role_exceeds_own_permissions');

        $this->assertSame($message, $this->put(self::BASE . "/{$victim->id}/reset-password", ['password' => 'Takeover#1'], $actor->token)->assertCode(400)->message());
        $this->assertSame($message, $this->put(self::BASE . "/{$victim->id}", ['password' => 'Takeover#2'], $actor->token)->assertCode(400)->message());
        $this->assertSame($hash, (string) Db::table('admins')->where('id', $victim->id)->value('password'), '两条路径都没写进新密码');

        $this->assertSame($message, $this->put(self::BASE . "/{$victim->id}/status", ['status' => 0], $actor->token)->assertCode(400)->message());
        $this->assertSame(1, (int) Db::table('admins')->where('id', $victim->id)->value('status'));

        $this->assertSame($message, $this->delete(self::BASE . "/{$victim->id}", [], $actor->token)->assertCode(400)->message());
        $this->assertNull(Db::table('admins')->where('id', $victim->id)->value('deleted_at'));

        // 正对照，界定这道新防线的位置：同一个目标，只改昵称、角色与部门原样回传，照常放行
        $this->put(self::BASE . "/{$victim->id}", ['nickname' => '只改昵称', 'role_ids' => $this->roleIdsOf($victim->id), 'department_id' => $dept], $actor->token)->assertOk();
        $this->assertSame('只改昵称', Db::table('admins')->where('id', $victim->id)->value('nickname'));
    }

    /** 同一道防线的另一半：目标的数据范围比自己大（持「全部」角色）时同样不能接管；超管四个操作都不受限。 */
    public function test_scope_limited_actor_cannot_take_over_a_wider_scope_admin_but_a_super_can(): void
    {
        $dept = $this->createDepartment(['name' => '接管范围B']);
        $actor = $this->actingAsAdmin(['system.admin.update', 'system.admin.status', 'system.admin.delete'], ['department_id' => $dept], ['data_scope' => DataScope::DEPT]);
        $victim = $this->actingAsAdmin([], ['department_id' => $dept], ['data_scope' => DataScope::ALL]);
        $message = lang('business.role_scope_exceeds_own');

        $this->assertSame($message, $this->put(self::BASE . "/{$victim->id}/reset-password", ['password' => 'Takeover#3'], $actor->token)->assertCode(400)->message());
        $this->assertSame($message, $this->put(self::BASE . "/{$victim->id}", ['password' => 'Takeover#4'], $actor->token)->assertCode(400)->message());
        $this->assertSame($message, $this->put(self::BASE . "/{$victim->id}/status", ['status' => 0], $actor->token)->assertCode(400)->message());
        $this->assertSame($message, $this->delete(self::BASE . "/{$victim->id}", [], $actor->token)->assertCode(400)->message());
        $this->assertNull(Db::table('admins')->where('id', $victim->id)->value('deleted_at'));

        $super = $this->actingAsAdmin('super');
        $this->put(self::BASE . "/{$victim->id}/reset-password", ['password' => 'Reset#9876'], $super->token)->assertOk();
        $this->put(self::BASE . "/{$victim->id}", ['password' => 'Reset#5432'], $super->token)->assertOk();
        $this->put(self::BASE . "/{$victim->id}/status", ['status' => 0], $super->token)->assertOk();
        $this->delete(self::BASE . "/{$victim->id}", [], $super->token)->assertOk();
        $this->assertNotNull(Db::table('admins')->where('id', $victim->id)->value('deleted_at'));
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

    /**
     * 32732cb 之后 Permission::isSuperAdmin() join 了 admins.status=1：禁用的超管在它眼里「不是超管」，
     * assertCanModify() 原先直接拿它判断目标，防线因此失效——非超管一次 PUT 改密码 + 启用就能接管被禁用的
     * 超管账号。修复后目标改按角色判定（roleRepository->adminHoldsSystemRole），不看账号状态。
     */
    public function test_non_super_cannot_take_over_a_disabled_super_admin(): void
    {
        $actor = $this->actingAsAdmin(['system.admin.update', 'system.admin.status']);
        $target = $this->actingAsAdmin('super', ['status' => 0]);
        $originalHash = (string) Db::table('admins')->where('id', $target->id)->value('password');

        // 1. 一次更新同时带密码与启用：两者都不能生效
        $this->assertSame(lang('auth.super_admin_no_modify'), $this->put(self::BASE . "/{$target->id}", ['password' => 'Takeover#1', 'status' => 1], $actor->token)->assertCode(400)->message());
        $this->assertSame(0, (int) Db::table('admins')->where('id', $target->id)->value('status'));
        $this->assertSame($originalHash, (string) Db::table('admins')->where('id', $target->id)->value('password'));

        // 2. 单走状态接口启用
        $this->assertSame(lang('auth.super_admin_no_modify'), $this->put(self::BASE . "/{$target->id}/status", ['status' => 1], $actor->token)->assertCode(400)->message());
        $this->assertSame(0, (int) Db::table('admins')->where('id', $target->id)->value('status'));

        // 3. 单独重置密码
        $this->assertSame(lang('auth.super_admin_no_modify'), $this->put(self::BASE . "/{$target->id}/reset-password", ['password' => 'Takeover#2'], $actor->token)->assertCode(400)->message());
        $this->assertSame($originalHash, (string) Db::table('admins')->where('id', $target->id)->value('password'));

        // 4. 正对照：超管本人可以把它重新启用
        $super = $this->actingAsAdmin('super');
        $this->put(self::BASE . "/{$target->id}/status", ['status' => 1], $super->token)->assertOk();
        $this->assertSame(1, (int) Db::table('admins')->where('id', $target->id)->value('status'));
    }

    /** 目标已经是启用中的超管：把它的状态设成 1 对它是空操作，但也不能靠「反正状态没变」绕过 assertCanModify()。 */
    public function test_non_super_with_status_permission_cannot_noop_enable_an_enabled_super_admin(): void
    {
        $actor = $this->actingAsAdmin(['system.admin.status']);
        $super = $this->actingAsAdmin('super');

        $this->assertSame(lang('auth.super_admin_no_modify'), $this->put(self::BASE . "/{$super->id}/status", ['status' => 1], $actor->token)->assertCode(400)->message());
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

        // updateStatus() 现在对任何 status 值都先跑 assertCanModify()（禁用的超管也受保护，见
        // test_non_super_cannot_take_over_a_disabled_super_admin），所以非超管碰超管账号一律先在
        // 这里被挡下，拿到的是更笼统的 super_admin_no_modify，走不到 assertCanDisable() 那句。
        $this->assertSame(lang('auth.super_admin_no_modify'), $this->put(self::BASE . "/{$super->id}/status", ['status' => 0], $actor->token)->assertCode(400)->message());
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
