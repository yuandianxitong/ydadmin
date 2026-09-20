<?php

declare(strict_types=1);

namespace tests\Feature\Article;

use tests\Support\ApiTestCase;

final class ArticleCategoryApiTest extends ApiTestCase
{
    public function test_list_is_tree_and_options_exclude_descendants(): void
    {
        $admin = $this->actingAsAdmin('super');
        $root = $this->post('/adminapi/article-category', ['name' => 'r'.bin2hex(random_bytes(3)), 'parent_id' => 0, 'status' => 1, 'sort' => 0], $admin->token)->assertOk()->data();
        $child = $this->post('/adminapi/article-category', ['name' => 'c'.bin2hex(random_bytes(3)), 'parent_id' => $root['id'], 'status' => 1, 'sort' => 0], $admin->token)->assertOk()->data();
        $this->track('article_categories', (int) $root['id']);
        $this->track('article_categories', (int) $child['id']);

        $tree = $this->get('/adminapi/article-category/list', [], $admin->token)->assertOk()->data();
        $this->assertIsList($tree);
        $hit = array_values(array_filter($tree, fn ($n) => (int) $n['id'] === (int) $root['id']));
        $this->assertNotSame([], $hit);
        $this->assertSame((int) $child['id'], (int) $hit[0]['children'][0]['id']);

        $opts = $this->get('/adminapi/article-category/options', ['exclude_id' => $root['id']], $admin->token)->assertOk()->data();
        $ids = $this->flattenIds($opts);
        $this->assertNotContains((int) $root['id'], $ids);
        $this->assertNotContains((int) $child['id'], $ids);
    }

    public function test_cannot_parent_to_descendant_or_delete_with_children(): void
    {
        $admin = $this->actingAsAdmin('super');
        $root = $this->post('/adminapi/article-category', ['name' => 'r'.bin2hex(random_bytes(3)), 'parent_id' => 0, 'status' => 1], $admin->token)->assertOk()->data();
        $child = $this->post('/adminapi/article-category', ['name' => 'c'.bin2hex(random_bytes(3)), 'parent_id' => $root['id'], 'status' => 1], $admin->token)->assertOk()->data();
        $this->track('article_categories', (int) $root['id']);
        $this->track('article_categories', (int) $child['id']);

        $this->put('/adminapi/article-category/'.$root['id'], ['parent_id' => $child['id'], 'name' => $root['name']], $admin->token)->assertCode(400);
        $this->delete('/adminapi/article-category/'.$root['id'], [], $admin->token)->assertCode(400);
        $this->post('/adminapi/article-category', ['name' => $root['name'], 'parent_id' => 0], $admin->token)->assertCode(400);
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return list<int>
     */
    private function flattenIds(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $node) {
            $ids[] = (int) $node['id'];
            $ids = [...$ids, ...$this->flattenIds($node['children'] ?? [])];
        }

        return $ids;
    }
}
