<?php

declare(strict_types=1);

namespace tests\Feature\DataScope;

use app\repository\system\AdminRepository;
use core\context\RequestContext;
use core\datascope\DataScope;
use core\datascope\DataScopeResolver;
use core\datascope\DataScopeSnapshot;
use support\Container;
use support\Context;
use support\Db;
use tests\Support\ApiTestCase;

final class DataScopeTest extends ApiTestCase
{
    private int $deptA;

    private int $deptA1;

    private int $deptB;

    /** @var array<string, int> 名字 → 管理员 id */
    private array $targets = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->deptA = $this->createDepartment(['name' => 'DS-A']);
        $this->deptA1 = $this->createDepartment(['name' => 'DS-A1', 'parent_id' => $this->deptA]);
        $this->deptB = $this->createDepartment(['name' => 'DS-B']);
        $this->targets = [
            'a'    => $this->actingAsAdmin([], ['department_id' => $this->deptA])->id,
            'a1'   => $this->actingAsAdmin([], ['department_id' => $this->deptA1])->id,
            'b'    => $this->actingAsAdmin([], ['department_id' => $this->deptB])->id,
            'none' => $this->actingAsAdmin()->id,
        ];
    }

    /** 模拟一个新请求：以 $viewerId 身份查询，返回可见的目标名（按 targets 顺序）。 @return list<string> */
    private function visibleAs(int $viewerId): array
    {
        Context::destroy();
        RequestContext::setActingUser($viewerId);
        $ids = (new AdminRepository())->visibleIds(array_values($this->targets));

        return array_keys(array_intersect($this->targets, $ids));
    }

    /** @param array<string, mixed> $role @param list<int> $deptIds */
    private function attachExtraRole(int $adminId, array $role, array $deptIds = []): void
    {
        $now = date('Y-m-d H:i:s');
        $roleId = (int) Db::table('roles')->insertGetId(array_merge([
            'name' => 'ds_extra_' . bin2hex(random_bytes(3)), 'title' => '附加角色', 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
        ], $role));
        $this->track('roles', $roleId);
        Db::table('admin_roles')->insert(['admin_id' => $adminId, 'role_id' => $roleId, 'created_at' => $now, 'updated_at' => $now]);
        foreach ($deptIds as $deptId) {
            Db::table('role_departments')->insert(['role_id' => $roleId, 'department_id' => $deptId, 'created_at' => $now, 'updated_at' => $now]);
        }
        Container::get(DataScopeResolver::class)->forget($adminId);
    }

    /**
     * 建一个不挂给任何人的角色（simulate() 用）。
     *
     * @param array<string, mixed> $attributes
     * @param list<int> $deptIds
     */
    private function looseRole(array $attributes, array $deptIds = []): int
    {
        $now = date('Y-m-d H:i:s');
        $roleId = (int) Db::table('roles')->insertGetId(array_merge([
            'name' => 'ds_sim_' . bin2hex(random_bytes(3)), 'title' => '模拟角色', 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
        ], $attributes));
        $this->track('roles', $roleId);
        foreach ($deptIds as $deptId) {
            Db::table('role_departments')->insert(['role_id' => $roleId, 'department_id' => $deptId, 'created_at' => $now, 'updated_at' => $now]);
        }

        return $roleId;
    }

    public function test_all_scope_and_super_admin_see_everything(): void
    {
        $all = $this->actingAsAdmin([], ['department_id' => $this->deptA], ['data_scope' => DataScope::ALL]);
        $this->assertSame(['a', 'a1', 'b', 'none'], $this->visibleAs($all->id));

        $super = $this->actingAsAdmin('super');
        $this->assertSame(['a', 'a1', 'b', 'none'], $this->visibleAs($super->id));
    }

    public function test_dept_scope_sees_own_department_only(): void
    {
        $viewer = $this->actingAsAdmin([], ['department_id' => $this->deptA], ['data_scope' => DataScope::DEPT]);
        $this->assertSame(['a'], $this->visibleAs($viewer->id));
    }

    public function test_dept_and_children_scope_includes_descendants(): void
    {
        $viewer = $this->actingAsAdmin([], ['department_id' => $this->deptA], ['data_scope' => DataScope::DEPT_AND_CHILDREN]);
        $this->assertSame(['a', 'a1'], $this->visibleAs($viewer->id));
    }

    public function test_self_scope_sees_only_itself(): void
    {
        $viewer = $this->actingAsAdmin([], ['department_id' => $this->deptA], ['data_scope' => DataScope::SELF]);
        Context::destroy();
        RequestContext::setActingUser($viewer->id);

        $this->assertSame([$viewer->id], (new AdminRepository())->visibleIds([...array_values($this->targets), $viewer->id]));
    }

    public function test_custom_scope_uses_role_departments(): void
    {
        $viewer = $this->actingAsAdmin([], [], ['data_scope' => DataScope::CUSTOM, 'dept_ids' => [$this->deptB]]);
        $this->assertSame(['b'], $this->visibleAs($viewer->id));
    }

    public function test_dept_scope_without_department_sees_nothing(): void
    {
        $viewer = $this->actingAsAdmin([], [], ['data_scope' => DataScope::DEPT]);
        $this->assertSame([], $this->visibleAs($viewer->id));

        Context::destroy();
        RequestContext::setActingUser($viewer->id);
        $this->assertSame(0, (new AdminRepository())->count(), '部门为空且非「仅本人」→ 恒假');
    }

    public function test_multiple_roles_are_merged(): void
    {
        $viewer = $this->actingAsAdmin([], ['department_id' => $this->deptA], ['data_scope' => DataScope::SELF]);
        $this->attachExtraRole($viewer->id, ['data_scope' => DataScope::CUSTOM], [$this->deptB]);
        Context::destroy();
        RequestContext::setActingUser($viewer->id);

        $visible = (new AdminRepository())->visibleIds([...array_values($this->targets), $viewer->id]);
        $this->assertEqualsCanonicalizing([$this->targets['b'], $viewer->id], $visible);

        // 钉住条件分组：viewer 自己在 deptA，但它的范围是「仅本人」+ 部门 B，deptA 里的
        // 'a' 不在这个并集里。数据权限条件（dept IN (B) OR id = viewer）必须作为一组与调用方的
        // id IN (...) 相与（DataScopeScope 以全局作用域挂载，由 Eloquent 分组）；分组一旦散开，
        // 'a' 会被误放行。
        Context::destroy();
        RequestContext::setActingUser($viewer->id);
        $this->assertSame([], (new AdminRepository())->visibleIds([$this->targets['a']]));
    }

    public function test_disabled_or_deleted_all_scope_role_is_ignored(): void
    {
        $viewer = $this->actingAsAdmin([], ['department_id' => $this->deptA], ['data_scope' => DataScope::DEPT]);
        $this->attachExtraRole($viewer->id, ['data_scope' => DataScope::ALL, 'status' => 0]);
        $this->attachExtraRole($viewer->id, ['data_scope' => DataScope::ALL, 'deleted_at' => date('Y-m-d H:i:s')]);
        Container::get(DataScopeResolver::class)->forget($viewer->id);

        $this->assertSame(['a'], $this->visibleAs($viewer->id));
    }

    public function test_no_acting_user_and_bypass_are_unrestricted(): void
    {
        $viewer = $this->actingAsAdmin([], ['department_id' => $this->deptA], ['data_scope' => DataScope::DEPT]);
        $repo = new AdminRepository();
        $ids = array_values($this->targets);

        Context::destroy();
        $this->assertCount(4, $repo->visibleIds($ids), '无管理员身份（CLI、队列、C 端）不过滤');

        RequestContext::setActingUser($viewer->id);
        $this->assertCount(1, $repo->visibleIds($ids));
        $counts = DataScope::bypass(function () use ($repo, $ids): array {
            $inner = DataScope::bypass(fn (): int => count($repo->visibleIds($ids)));

            return [$inner, count($repo->visibleIds($ids))];
        });
        $this->assertSame([4, 4], $counts, '内层 bypass 退出后外层仍然生效');
        $this->assertCount(1, $repo->visibleIds($ids), 'bypass 结束后恢复过滤');
    }

    public function test_pagination_total_counts_only_visible_rows(): void
    {
        $viewer = $this->actingAsAdmin([], ['department_id' => $this->deptA], ['data_scope' => DataScope::DEPT_AND_CHILDREN]);
        Context::destroy();
        RequestContext::setActingUser($viewer->id);

        $ids = array_values($this->targets);
        $result = (new AdminRepository())->getListWithRoles([[static fn ($q) => $q->whereIn('admins.id', $ids)]], 1, 1);
        $this->assertSame(2, $result['pagination']['total']);
        $this->assertSame(2, $result['pagination']['last_page']);
    }

    public function test_created_by_is_filled_on_scoped_create(): void
    {
        $viewer = $this->actingAsAdmin([], ['department_id' => $this->deptA]);
        Context::destroy();
        RequestContext::setActingUser($viewer->id);

        $row = (new AdminRepository())->create(['username' => 'ds_' . bin2hex(random_bytes(3)), 'password' => 'x', 'status' => 1]);
        $this->trackAdmin((int) $row['id']);
        $this->assertSame($viewer->id, (int) Db::table('admins')->where('id', $row['id'])->value('created_by'));
    }

    public function test_resolver_snapshot_is_cached_until_forgotten(): void
    {
        $viewer = $this->actingAsAdmin([], ['department_id' => $this->deptA], ['data_scope' => DataScope::DEPT]);
        $resolver = Container::get(DataScopeResolver::class);
        $roleId = (int) Db::table('admin_roles')->where('admin_id', $viewer->id)->value('role_id');

        $this->assertSame([$this->deptA], $resolver->resolve($viewer->id)->deptIds);
        Db::table('roles')->where('id', $roleId)->update(['data_scope' => DataScope::ALL]);
        $this->assertFalse($resolver->resolve($viewer->id)->all, '命中缓存');
        $resolver->forget($viewer->id);
        $this->assertTrue($resolver->resolve($viewer->id)->all);

        Db::table('roles')->where('id', $roleId)->update(['data_scope' => DataScope::DEPT]);
        $resolver->forgetAll();
        $this->assertFalse($resolver->resolve($viewer->id)->all);
    }

    public function test_simulate_computes_like_resolve_without_touching_the_cache(): void
    {
        $viewer = $this->actingAsAdmin([], ['department_id' => $this->deptA], ['data_scope' => DataScope::DEPT_AND_CHILDREN]);
        $resolver = Container::get(DataScopeResolver::class);
        $roleIds = array_map('intval', Db::table('admin_roles')->where('admin_id', $viewer->id)->pluck('role_id')->all());

        $this->assertEquals($resolver->resolve($viewer->id), $resolver->simulate($viewer->id, $this->deptA, $roleIds));

        // 预演换到部门 B：只是计算，不改库，也不写这个管理员的缓存
        $resolver->forget($viewer->id);
        $this->assertSame([$this->deptB], $resolver->simulate($viewer->id, $this->deptB, $roleIds)->deptIds);
        $this->assertSame([$this->deptA, $this->deptA1], $resolver->resolve($viewer->id)->deptIds);
    }

    public function test_simulate_counts_only_enabled_undeleted_roles(): void
    {
        $resolver = Container::get(DataScopeResolver::class);
        $disabledAll = $this->looseRole(['data_scope' => DataScope::ALL, 'status' => 0]);
        $deletedAll = $this->looseRole(['data_scope' => DataScope::ALL, 'deleted_at' => date('Y-m-d H:i:s')]);
        $custom = $this->looseRole(['data_scope' => DataScope::CUSTOM], [$this->deptB]);
        $self = $this->looseRole(['data_scope' => DataScope::SELF]);

        $snapshot = $resolver->simulate(0, $this->deptA, [$disabledAll, $deletedAll, $custom, $self, $custom]);
        $this->assertFalse($snapshot->all, '禁用、已删的「全部」角色不算');
        $this->assertSame([$this->deptB], $snapshot->deptIds);
        $this->assertTrue($snapshot->self);

        $this->assertTrue($resolver->simulate(0, null, [1])->all, '系统角色 → 全部');
        $this->assertEquals(new DataScopeSnapshot(false, [], false, 0), $resolver->simulate(0, $this->deptA, []));
    }
}
