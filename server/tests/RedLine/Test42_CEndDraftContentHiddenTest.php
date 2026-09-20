<?php

declare(strict_types=1);

namespace tests\RedLine;

use tests\Support\ApiTestCase;

/**
 * 红线（M7a）：C 端详情只认已发布。草稿文章 / 草稿公告对未登录与已登录会员一律 404，
 * 响应 data 不得带 content（不能靠「报了 404 但仍回正文」漏出草稿）。
 */
final class Test42_CEndDraftContentHiddenTest extends ApiTestCase
{
    public function test_guest_and_logged_in_user_cannot_read_draft_article_or_announcement(): void
    {
        $admin = $this->actingAsAdmin('super');
        $user = $this->actingAsUser();
        $suffix = bin2hex(random_bytes(6));

        $cat = $this->post('/adminapi/article-category', [
            'name'      => 'rl42-cat-' . $suffix,
            'parent_id' => 0,
            'status'    => 1,
        ], $admin->token)->assertOk()->data();
        $this->track('article_categories', (int) $cat['id']);

        $article = $this->post('/adminapi/article', [
            'title'       => 'rl42-article-' . $suffix,
            'category_id' => $cat['id'],
            'content'     => 'rl42-article-body-' . $suffix,
            'status'      => 0,
        ], $admin->token)->assertOk()->data();
        $this->track('articles', (int) $article['id']);

        $announcement = $this->post('/adminapi/announcement', [
            'title'   => 'rl42-announcement-' . $suffix,
            'content' => 'rl42-announcement-body-' . $suffix,
            'type'    => 1,
            'status'  => 0,
            'sort'    => 0,
        ], $admin->token)->assertOk()->data();
        $this->track('announcements', (int) $announcement['id']);

        $paths = [
            '/api/article/detail/' . $article['id'],
            '/api/announcement/detail/' . $announcement['id'],
        ];
        foreach ($paths as $path) {
            foreach ([null, $user->token] as $token) {
                $response = $this->get($path, [], $token);
                $response->assertCode(404);
                $this->assertArrayNotHasKey('content', $response->data());
            }
        }
    }
}
