<?php

declare(strict_types=1);

namespace tests\RedLine;

use support\Db;
use tests\Support\ApiTestCase;

/**
 * 红线（M7c）：C 端装修只认已发布且启用的页面。
 *
 * 仅草稿未发布 → 404；发布后 → 200；status=0 禁用后 → 404。
 * 404 的完整响应字符串不得含草稿组件 id（不能「报了 404 但仍回草稿」）。
 */
final class Test52_CEndUnpublishedDiyHiddenTest extends ApiTestCase
{
    public function test_disabled_or_empty_published_is_hidden_from_c_end(): void
    {
        $admin = $this->actingAsAdmin('super');
        $key = 'rl-' . bin2hex(random_bytes(3));
        $id = (int) $this->post('/adminapi/diy/pages', ['title' => '红线', 'page_key' => $key], $admin->token)
            ->assertCode(200)->data()['id'];
        $this->track('diy_pages', $id);

        $this->put("/adminapi/diy/pages/{$key}/draft", [
            'components'    => [['id' => 'secret-draft', 'type' => 'banner', 'props' => []]],
            'page_settings' => [],
        ], $admin->token)->assertCode(200);
        $this->get('/api/mobile/diy-page', ['key' => $key])->assertCode(404);

        $this->post("/adminapi/diy/pages/{$key}/publish", [], $admin->token)->assertCode(200);
        foreach (Db::table('diy_page_versions')->where('page_id', $id)->pluck('id') as $vid) {
            $this->track('diy_page_versions', (int) $vid);
        }
        $this->get('/api/mobile/diy-page', ['key' => $key])->assertCode(200);

        $this->put("/adminapi/diy/pages/{$id}", ['status' => 0], $admin->token)->assertCode(200);
        $hidden = $this->get('/api/mobile/diy-page', ['key' => $key]);
        $hidden->assertCode(404);
        $this->assertStringNotContainsString('secret-draft', $hidden->body());
    }
}
