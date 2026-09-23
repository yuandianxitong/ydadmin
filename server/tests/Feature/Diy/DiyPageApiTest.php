<?php

declare(strict_types=1);

namespace tests\Feature\Diy;

use support\Db;
use tests\Support\ApiTestCase;

final class DiyPageApiTest extends ApiTestCase
{
    public function test_custom_crud_copy_and_system_page_protected(): void
    {
        $admin = $this->actingAsAdmin('super');
        $suffix = bin2hex(random_bytes(3));
        $key = 'p-' . $suffix;

        $this->post('/adminapi/diy/pages', ['title' => 'A', 'page_key' => 'home'], $admin->token)->assertCode(422);
        $this->post('/adminapi/diy/pages', ['title' => 'A', 'page_key' => 'Bad_Key'], $admin->token)->assertCode(422);

        $created = $this->post('/adminapi/diy/pages', ['title' => '页面A', 'page_key' => $key], $admin->token)
            ->assertCode(200)->data();
        $this->track('diy_pages', (int) $created['id']);
        $this->assertArrayHasKey('id', $created);

        $this->post('/adminapi/diy/pages', ['title' => '撞车', 'page_key' => $key], $admin->token)->assertCode(422);

        $list = $this->get('/adminapi/diy/pages', ['page' => 1, 'limit' => 10, 'keyword' => '页面A'], $admin->token)
            ->assertCode(200)->data();
        $this->assertArrayHasKey('list', $list);
        $this->assertArrayHasKey('total', $list);
        $this->assertArrayNotHasKey('pagination', $list);

        $this->put('/adminapi/diy/pages/' . $key . '/draft', [
            'components' => [['id' => 'c1', 'type' => 'rich-text', 'props' => ['html' => '<p>x</p>']]],
            'page_settings' => [],
        ], $admin->token)->assertCode(200);
        $this->post('/adminapi/diy/pages/' . $key . '/publish', [], $admin->token)->assertCode(200);
        foreach (Db::table('diy_page_versions')->where('page_id', $created['id'])->pluck('id') as $vid) {
            $this->track('diy_page_versions', (int) $vid);
        }

        $copy1 = $this->post('/adminapi/diy/pages/' . $created['id'] . '/copy', [], $admin->token)->assertCode(200)->data();
        $this->track('diy_pages', (int) $copy1['id']);
        $row1 = Db::table('diy_pages')->where('id', $copy1['id'])->first();
        $this->assertSame($key . '-copy', $row1->page_key);
        $pub1 = json_decode((string) $row1->components_published, true);
        $this->assertTrue($pub1 === null || $pub1 === []);

        $copy2 = $this->post('/adminapi/diy/pages/' . $created['id'] . '/copy', [], $admin->token)->assertCode(200)->data();
        $this->track('diy_pages', (int) $copy2['id']);
        $row2 = Db::table('diy_pages')->where('id', $copy2['id'])->first();
        $this->assertSame($key . '-copy2', $row2->page_key);

        $homeId = (int) Db::table('diy_pages')->where('page_key', 'home')->whereNull('deleted_at')->value('id');
        $this->delete('/adminapi/diy/pages/' . $homeId, [], $admin->token)->assertCode(400);
        $this->assertNotNull(Db::table('diy_pages')->where('id', $homeId)->whereNull('deleted_at')->first());

        $this->delete('/adminapi/diy/pages/' . $created['id'], [], $admin->token)->assertCode(200);
        $this->assertNotNull(Db::table('diy_pages')->where('id', $created['id'])->whereNotNull('deleted_at')->first());
        $this->post('/adminapi/diy/pages', ['title' => '复用', 'page_key' => $key], $admin->token)->assertCode(422);
    }

    /** 组件 id / type 传数组时同样要 422。 */
    public function test_component_array_values_are_rejected_with_422(): void
    {
        $admin = $this->actingAsAdmin('super');
        $key = 'arr-' . bin2hex(random_bytes(3));
        $created = $this->post('/adminapi/diy/pages', ['title' => '数组页', 'page_key' => $key], $admin->token)->assertOk()->data();
        $this->track('diy_pages', (int) $created['id']);

        $response = $this->put('/adminapi/diy/pages/' . $key . '/draft', [
            'components'    => [['id' => ['a' => 1], 'type' => 'banner', 'props' => []]],
            'page_settings' => [],
        ], $admin->token);
        $this->assertSame(200, $response->status(), '不能是未捕获异常');
        $this->assertSame(422, $response->code());
    }
}
