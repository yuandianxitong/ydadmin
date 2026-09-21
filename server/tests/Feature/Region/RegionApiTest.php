<?php

declare(strict_types=1);

namespace tests\Feature\Region;

use support\Db;
use tests\Support\ApiTestCase;

final class RegionApiTest extends ApiTestCase
{
    public function test_create_recomputes_level_and_rejects_duplicate_code(): void
    {
        $admin = $this->actingAsAdmin('super');
        $code = 't' . bin2hex(random_bytes(4));
        $root = $this->post('/adminapi/region', [
            'parent_id' => 0, 'name' => 'r' . $code, 'code' => $code,
            'level' => 9, 'sort' => 0, 'status' => 1,
        ], $admin->token)->assertOk()->data();
        $this->track('regions', (int) $root['id']);
        $this->assertSame(1, (int) $root['level']);

        $childCode = 't' . bin2hex(random_bytes(4));
        $child = $this->post('/adminapi/region', [
            'parent_id' => $root['id'], 'name' => 'c' . $childCode, 'code' => $childCode,
            'level' => 9, 'sort' => 0, 'status' => 1,
        ], $admin->token)->assertOk()->data();
        $this->track('regions', (int) $child['id']);
        $this->assertSame(2, (int) $child['level']);

        $this->post('/adminapi/region', [
            'parent_id' => 0, 'name' => 'dup', 'code' => $code, 'status' => 1,
        ], $admin->token)->assertCode(422);
    }

    public function test_cannot_parent_to_descendant_or_delete_with_children(): void
    {
        $admin = $this->actingAsAdmin('super');
        $p = 't' . bin2hex(random_bytes(3));
        $root = $this->post('/adminapi/region', ['parent_id' => 0, 'name' => 'r' . $p, 'code' => 'r' . $p, 'status' => 1], $admin->token)->assertOk()->data();
        $child = $this->post('/adminapi/region', ['parent_id' => $root['id'], 'name' => 'c' . $p, 'code' => 'c' . $p, 'status' => 1], $admin->token)->assertOk()->data();
        $this->track('regions', (int) $root['id']);
        $this->track('regions', (int) $child['id']);

        $this->put('/adminapi/region/' . $root['id'], ['parent_id' => $child['id'], 'name' => $root['name'], 'code' => $root['code']], $admin->token)->assertCode(400);
        $this->delete('/adminapi/region/' . $root['id'], [], $admin->token)->assertCode(400);
        $this->assertNotNull(Db::table('regions')->where('id', $root['id'])->first());
    }

    public function test_tree_and_children_hide_disabled(): void
    {
        $admin = $this->actingAsAdmin('super');
        $p = 't' . bin2hex(random_bytes(3));
        $on = $this->post('/adminapi/region', ['parent_id' => 0, 'name' => 'on' . $p, 'code' => 'on' . $p, 'status' => 1], $admin->token)->assertOk()->data();
        $off = $this->post('/adminapi/region', ['parent_id' => 0, 'name' => 'off' . $p, 'code' => 'off' . $p, 'status' => 0], $admin->token)->assertOk()->data();
        $orphan = $this->post('/adminapi/region', ['parent_id' => $off['id'], 'name' => 'or' . $p, 'code' => 'or' . $p, 'status' => 1], $admin->token)->assertOk()->data();
        $this->track('regions', (int) $on['id']);
        $this->track('regions', (int) $off['id']);
        $this->track('regions', (int) $orphan['id']);

        $list = $this->get('/adminapi/region/list', ['parent_id' => 0, 'page' => 1, 'limit' => 100], $admin->token)->assertOk()->data()['list'];
        $listIds = array_map('intval', array_column($list, 'id'));
        $this->assertContains((int) $off['id'], $listIds);

        $underOff = $this->get('/adminapi/region/list', ['parent_id' => $off['id'], 'page' => 1, 'limit' => 100], $admin->token)->assertOk()->data()['list'];
        $this->assertContains((int) $orphan['id'], array_map('intval', array_column($underOff, 'id')));

        foreach (['/adminapi/region/tree', '/adminapi/common/regions', '/api/region/tree'] as $path) {
            $tree = $path === '/api/region/tree'
                ? $this->get($path)->assertOk()->data()
                : $this->get($path, [], $admin->token)->assertOk()->data();
            $this->assertIsList($tree);
            $values = $this->flattenValues($tree);
            $this->assertContains((int) $on['id'], $values);
            $this->assertNotContains((int) $off['id'], $values);
            $this->assertNotContains((int) $orphan['id'], $values);
            $this->assertArrayHasKey('value', $tree[0]);
            $this->assertArrayHasKey('label', $tree[0]);
        }

        $kids = $this->get('/api/region/children', ['parent_id' => 0])->assertOk()->data();
        $kidIds = array_map('intval', array_column($kids, 'id'));
        $this->assertContains((int) $on['id'], $kidIds);
        $this->assertNotContains((int) $off['id'], $kidIds);
        $this->assertArrayHasKey('name', $kids[0]);
        $this->assertArrayNotHasKey('value', $kids[0]);
    }

    /** @param list<array<string, mixed>> $nodes @return list<int> */
    private function flattenValues(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $node) {
            $ids[] = (int) $node['value'];
            $ids = [...$ids, ...$this->flattenValues($node['children'] ?? [])];
        }
        return $ids;
    }
}
