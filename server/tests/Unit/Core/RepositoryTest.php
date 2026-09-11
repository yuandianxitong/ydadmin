<?php

declare(strict_types=1);

namespace tests\Unit\Core;

use core\base\Model;
use core\base\Repository;
use Illuminate\Database\Schema\Blueprint;
use support\Db;
use tests\TestCase;

final class RepoItemModel extends Model
{
    protected $table = 'test_repo_items';
}

final class RepoItemRepository extends Repository
{
    protected function getModel(): Model
    {
        return new RepoItemModel();
    }
}

final class RepositoryTest extends TestCase
{
    private RepoItemRepository $repo;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        Db::schema()->dropIfExists('test_repo_items');
        Db::schema()->create('test_repo_items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('sort')->default(0);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    public static function tearDownAfterClass(): void
    {
        Db::schema()->dropIfExists('test_repo_items');
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Db::table('test_repo_items')->truncate();
        $this->repo = new RepoItemRepository();
    }

    private function seed(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->repo->create(['name' => "item-{$i}", 'sort' => $i % 2]);
        }
    }

    public function test_create_returns_row_with_id_and_timestamps(): void
    {
        $row = $this->repo->create(['name' => 'a']);
        $this->assertIsInt($row['id']);
        $this->assertSame('a', $row['name']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['created_at']);
    }

    public function test_find_update_delete(): void
    {
        $id = $this->repo->create(['name' => 'a'])['id'];

        $this->assertSame('a', $this->repo->find($id)['name']);
        $this->assertTrue($this->repo->update($id, ['name' => 'b']));
        $this->assertSame('b', $this->repo->find($id)['name']);
        $this->assertFalse($this->repo->update(999999, ['name' => 'x']));

        $this->assertTrue($this->repo->delete($id));
        $this->assertNull($this->repo->find($id));
        $this->assertFalse($this->repo->delete($id));
    }

    public function test_get_list_pagination_shape(): void
    {
        $this->seed(5);
        $result = $this->repo->getList([], 2, 2, 'id asc');

        $this->assertSame(['item-3', 'item-4'], array_column($result['list'], 'name'));
        $this->assertSame(['current_page' => 2, 'per_page' => 2, 'total' => 5, 'last_page' => 3], $result['pagination']);
    }

    public function test_get_list_normalizes_non_positive_page_and_limit(): void
    {
        $this->seed(3);
        $result = $this->repo->getList([], 0, 0);
        $this->assertSame(1, $result['pagination']['current_page']);
        $this->assertSame(1, $result['pagination']['per_page']);
    }

    public function test_get_all_supports_multi_column_order(): void
    {
        $this->seed(4); // sort: 1,0,1,0
        $names = array_column($this->repo->getAll([], 'sort asc, id desc'), 'name');
        $this->assertSame(['item-4', 'item-2', 'item-3', 'item-1'], $names);
    }

    public function test_invalid_order_direction_falls_back_to_asc(): void
    {
        $this->seed(2);
        $names = array_column($this->repo->getAll([], 'id sideways'), 'name');
        $this->assertSame(['item-1', 'item-2'], $names);
    }

    public function test_aggregate_helpers(): void
    {
        $this->seed(3);
        $this->assertSame(3, $this->repo->count());
        $this->assertSame(2, $this->repo->count(['sort' => 1]));
        $this->assertTrue($this->repo->exists(['name' => 'item-2']));
        $this->assertSame('item-1', $this->repo->value(['sort' => 1], 'name'));
        $this->assertSame(['item-1', 'item-3'], $this->repo->column(['sort' => 1], 'name'));
        $this->assertSame(1, $this->repo->inc(['name' => 'item-2'], 'sort', 5));
        $this->assertSame(5, (int) $this->repo->value(['name' => 'item-2'], 'sort'));
    }
}
