<?php

declare(strict_types=1);

namespace tests\Feature\DataScope;

use app\repository\system\AdminRepository;
use core\context\RequestContext;
use core\datascope\DataScope;
use Illuminate\Database\Eloquent\Builder;
use support\Context;
use support\Db;
use tests\Support\ApiTestCase;

/** 测试专用：在受控仓储上故意写一个顶层 orWhere（业务代码仍约定包进闭包）。 */
final class TopLevelOrAdminRepository extends AdminRepository
{
    /** @return list<int> */
    public function idsByUsernameOrId(string $username, int $id): array
    {
        return array_map('intval', $this->query()
            ->where($this->qualify('username'), $username)
            ->orWhere($this->qualify('id'), $id)
            ->pluck($this->qualify('id'))
            ->all());
    }

    /** @return Builder<\core\base\Model> */
    public function exposedQuery(): Builder
    {
        return $this->query();
    }
}

/** 测试专用：表里有 created_by，但仓储声明不自动填创建人。 */
final class NoCreatorAdminRepository extends AdminRepository
{
    protected ?string $creatorColumn = null;
}

/** 测试专用：创建人写到 updated_by，验证 $creatorColumn 决定填哪一列。 */
final class UpdatedByCreatorAdminRepository extends AdminRepository
{
    protected ?string $creatorColumn = 'updated_by';
}

final class DataScopeGlobalScopeTest extends ApiTestCase
{
    private int $inScope;

    private int $outOfScope;

    private int $viewerId;

    protected function setUp(): void
    {
        parent::setUp();
        $deptA = $this->createDepartment(['name' => 'GS-A']);
        $deptB = $this->createDepartment(['name' => 'GS-B']);
        $this->inScope = $this->actingAsAdmin([], ['department_id' => $deptA])->id;
        $this->outOfScope = $this->actingAsAdmin([], ['department_id' => $deptB])->id;
        $this->viewerId = $this->actingAsAdmin([], ['department_id' => $deptA], ['data_scope' => DataScope::DEPT])->id;
    }

    /** 模拟一个新请求：之后的查询以「本部门」范围的 viewer 身份执行。 */
    private function actAsViewer(): void
    {
        Context::destroy();
        RequestContext::setActingUser($this->viewerId);
    }

    public function test_top_level_or_from_the_caller_cannot_escape_the_scope(): void
    {
        $this->actAsViewer();
        $repo = new TopLevelOrAdminRepository();

        $this->assertSame([], $repo->idsByUsernameOrId('no-such-user', $this->outOfScope), '顶层 orWhere 不得把范围外的行带出来');

        $inScopeName = (string) Db::table('admins')->where('id', $this->inScope)->value('username');
        $this->assertSame([$this->inScope], $repo->idsByUsernameOrId($inScopeName, $this->outOfScope));
    }

    public function test_scope_is_fixed_when_the_query_is_built(): void
    {
        $repo = new TopLevelOrAdminRepository();
        $ids = [$this->inScope, $this->outOfScope];
        $this->actAsViewer();

        $scoped = $repo->exposedQuery()->whereIn('admins.id', $ids);
        $this->assertSame(
            [$this->inScope],
            DataScope::bypass(static fn (): array => array_map('intval', $scoped->pluck('admins.id')->all())),
            '建好的受控查询拿进 bypass 里执行，仍然受限',
        );

        $unscoped = DataScope::bypass(static fn (): Builder => $repo->exposedQuery()->whereIn('admins.id', $ids));
        $this->assertEqualsCanonicalizing($ids, array_map('intval', $unscoped->pluck('admins.id')->all()), 'bypass 里建的查询不挂作用域');
    }

    public function test_creator_column_null_skips_auto_fill(): void
    {
        $this->actAsViewer();
        $row = (new NoCreatorAdminRepository())->create(['username' => 'gs_' . bin2hex(random_bytes(3)), 'password' => 'x', 'status' => 1]);
        $this->trackAdmin((int) $row['id']);

        $this->assertNull(Db::table('admins')->where('id', $row['id'])->value('created_by'));
    }

    public function test_creator_column_names_the_auto_filled_column(): void
    {
        $this->actAsViewer();
        $row = (new UpdatedByCreatorAdminRepository())->create(['username' => 'gs_' . bin2hex(random_bytes(3)), 'password' => 'x', 'status' => 1]);
        $this->trackAdmin((int) $row['id']);

        $stored = Db::table('admins')->where('id', $row['id'])->first(['created_by', 'updated_by']);
        $this->assertSame($this->viewerId, (int) $stored->updated_by);
        $this->assertNull($stored->created_by);
    }
}
