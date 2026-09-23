<?php

declare(strict_types=1);

namespace tests\Feature\Article;

use support\Db;
use tests\Support\ApiTestCase;

final class ArticleApiTest extends ApiTestCase
{
    public function test_admin_create_sets_created_by_and_publish_at_without_incrementing_views(): void
    {
        $admin = $this->actingAsAdmin('super');
        $cat = $this->post('/adminapi/article-category', ['name' => 't'.bin2hex(random_bytes(3)), 'parent_id' => 0, 'status' => 1], $admin->token)->assertOk()->data();
        $this->track('article_categories', (int) $cat['id']);
        $created = $this->post('/adminapi/article', [
            'title' => 'Hello', 'category_id' => $cat['id'], 'content' => '<p>x</p>', 'status' => 1, 'tags' => ['a'],
        ], $admin->token)->assertOk()->data();
        $this->track('articles', (int) $created['id']);
        $this->assertSame($admin->id, (int) $created['created_by']);
        $this->assertNotEmpty($created['publish_at']);
        $this->assertSame(0, (int) $created['view_count']);
        $again = $this->get('/adminapi/article/detail/'.$created['id'], [], $admin->token)->assertOk()->data();
        $this->assertSame(0, (int) $again['view_count']);
        $this->assertSame($cat['name'], $again['category_name']);
    }

    /** 后台表单把选填项固定当空串发：封面/摘要/作者留空、草稿不选发布时间，都要能存下来。 */
    public function test_admin_form_payload_with_blank_optional_fields_saves(): void
    {
        $admin = $this->actingAsAdmin('super');
        $cat = $this->post('/adminapi/article-category', ['name' => 't'.bin2hex(random_bytes(3)), 'parent_id' => 0, 'status' => 1], $admin->token)->assertOk()->data();
        $this->track('article_categories', (int) $cat['id']);

        $created = $this->post('/adminapi/article', [
            'title'      => 'Blank',
            'category_id' => $cat['id'],
            'content'    => '<p>x</p>',
            'cover'      => '',
            'summary'    => '',
            'author'     => '',
            'tags'       => [],
            'status'     => 0,
            'publish_at' => '',
        ], $admin->token)->assertOk()->data();
        $this->track('articles', (int) $created['id']);
        $this->assertSame('', (string) $created['cover']);
        $this->assertSame('', (string) $created['summary']);
        $this->assertSame('', (string) $created['author']);
        $this->assertNull($created['publish_at'], '草稿不填发布时间时存 null，不能把空串写进 datetime 列');

        $this->put('/adminapi/article/'.$created['id'], [
            'title'      => 'Blank2',
            'category_id' => $cat['id'],
            'content'    => '<p>y</p>',
            'cover'      => '',
            'summary'    => '',
            'author'     => '',
            'status'     => 0,
            'publish_at' => '',
        ], $admin->token)->assertOk();
        $this->assertNull(Db::table('articles')->where('id', $created['id'])->value('publish_at'));
    }

    public function test_c_end_hides_draft_and_increments_published_views(): void
    {
        $admin = $this->actingAsAdmin('super');
        $cat = $this->post('/adminapi/article-category', ['name' => 't'.bin2hex(random_bytes(3)), 'parent_id' => 0, 'status' => 1], $admin->token)->assertOk()->data();
        $this->track('article_categories', (int) $cat['id']);
        $draft = $this->post('/adminapi/article', ['title' => 'D', 'category_id' => $cat['id'], 'content' => 'c', 'status' => 0], $admin->token)->assertOk()->data();
        $pub = $this->post('/adminapi/article', ['title' => 'P', 'category_id' => $cat['id'], 'content' => 'c', 'status' => 1], $admin->token)->assertOk()->data();
        $this->track('articles', (int) $draft['id']);
        $this->track('articles', (int) $pub['id']);

        $hidden = $this->get('/api/article/detail/'.$draft['id']);
        $hidden->assertCode(404);
        $this->assertArrayNotHasKey('content', $hidden->data());

        $body = $this->get('/api/article/detail/'.$pub['id'])->assertOk()->data();
        $this->assertSame(1, (int) $body['view_count']);
        $this->assertSame(1, (int) $body['views']);
        $this->assertSame($cat['name'], $body['category_name']);
        $this->get('/api/article/detail/'.$pub['id'])->assertOk();
        $this->assertSame(2, (int) Db::table('articles')->where('id', $pub['id'])->value('view_count'));

        $list = $this->get('/api/article/list', ['page_no' => 1, 'page_size' => 10])->assertOk()->data();
        $ids = array_column($list['list'], 'id');
        $this->assertContains((int) $pub['id'], $ids);
        $this->assertNotContains((int) $draft['id'], $ids);
    }
}
