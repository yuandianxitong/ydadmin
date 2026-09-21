<?php

declare(strict_types=1);

namespace tests\Feature\Diy;

use app\repository\article\ArticleRepository;
use support\Db;
use tests\Support\ApiTestCase;

final class DiyWidgetPreviewApiTest extends ApiTestCase
{
    public function test_preview_rejects_unknown_type_and_hydrates_content_list(): void
    {
        $admin = $this->actingAsAdmin('super');
        $this->post('/adminapi/diy/widget-preview', ['type' => 'nope', 'props' => []], $admin->token)
            ->assertCode(422);

        // articles 必填列来自 schema.sql / Article 模型：category_id、title、content（其余有默认值）
        $categoryId = (int) Db::table('article_categories')->value('id');
        if ($categoryId <= 0) {
            $now = date('Y-m-d H:i:s');
            $categoryId = (int) Db::table('article_categories')->insertGetId([
                'parent_id'  => 0,
                'name'       => '预览栏目',
                'icon'       => '',
                'sort'       => 0,
                'status'     => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->track('article_categories', $categoryId);
        }

        $now = date('Y-m-d H:i:s');
        $articleId = (int) Db::table('articles')->insertGetId([
            'title'       => '预览文',
            'category_id' => $categoryId,
            'content'     => '<p>x</p>',
            'status'      => ArticleRepository::PUBLISHED,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $this->track('articles', $articleId);

        $data = $this->post('/adminapi/diy/widget-preview', [
            'type'  => 'content-list',
            'props' => ['source' => 'latest', 'limit' => 6],
        ], $admin->token)->assertCode(200)->data();
        $this->assertArrayHasKey('props', $data);
        $this->assertNotSame([], $data['props']['items']);
        $this->assertSame('预览文', $data['props']['items'][0]['title']);

        $ok = $this->post('/adminapi/diy/widget-preview', [
            'type'  => 'banner',
            'props' => ['height' => 120],
        ], $admin->token)->assertCode(200)->data();
        $this->assertSame(120, $ok['props']['height']);
    }
}
