<?php

declare(strict_types=1);

namespace tests\Feature\Diy;

use support\Db;
use tests\Support\ApiTestCase;

final class DiyHomeApiTest extends ApiTestCase
{
    /** @var array{id: int, components_draft: mixed, components_published: mixed, page_settings: mixed}|null */
    private ?array $homeSeed = null;

    protected function setUp(): void
    {
        parent::setUp();
        $row = Db::table('diy_pages')->where('page_key', 'home')->whereNull('deleted_at')->first();
        if ($row === null) {
            return;
        }
        $this->homeSeed = [
            'id'                   => (int) $row->id,
            'components_draft'     => $row->components_draft,
            'components_published' => $row->components_published,
            'page_settings'        => $row->page_settings,
        ];
    }

    protected function tearDown(): void
    {
        $this->restoreHomeSeed();
        parent::tearDown();
    }

    public function test_home_draft_publish_version_restore_and_c_end(): void
    {
        $admin = $this->actingAsAdmin('super');

        $get = $this->get('/adminapi/diy/home', [], $admin->token)->assertCode(200);
        $this->assertArrayHasKey('components', $get->data());
        $this->assertArrayHasKey('page_settings', $get->data());

        $widgets = $this->get('/adminapi/diy/widgets', [], $admin->token)->assertCode(200)->data();
        $this->assertSame(\core\diy\DiyWidgetRegistry::TYPES, $widgets['builtins']);
        $this->assertSame([], $widgets['plugins']);
        $this->assertSame('user.balance', $widgets['member_stats'][0]['key']);

        $this->put('/adminapi/diy/home', [
            'components' => [
                ['id' => '', 'type' => 'banner', 'props' => []],
            ],
            'page_settings' => [],
        ], $admin->token)->assertCode(422);

        $draft = [
            ['id' => 't-banner', 'type' => 'banner', 'props' => ['height' => 200]],
        ];
        $this->put('/adminapi/diy/home', [
            'components'    => $draft,
            'page_settings' => ['background_color' => '#fff'],
        ], $admin->token)->assertCode(200);

        $this->post('/adminapi/diy/home/publish', [], $admin->token)->assertCode(200);

        $c = $this->get('/api/mobile/diy-page', ['key' => 'home'])->assertCode(200)->data();
        $this->assertSame('t-banner', $c['components'][0]['id']);
        $this->assertArrayNotHasKey('components_draft', $c);

        $versions = $this->get('/adminapi/diy/home/versions', [], $admin->token)->assertCode(200)->data();
        $this->assertIsArray($versions);
        $this->assertGreaterThanOrEqual(1, count($versions));
        $this->assertArrayHasKey('version_no', $versions[0]);

        $this->put('/adminapi/diy/home', [
            'components' => [
                ['id' => 't-notice', 'type' => 'notice', 'props' => ['items' => []]],
            ],
            'page_settings' => [],
        ], $admin->token)->assertCode(200);

        $this->post('/adminapi/diy/home/versions/' . $versions[0]['id'] . '/restore', [], $admin->token)->assertCode(200);
        $after = $this->get('/adminapi/diy/home', [], $admin->token)->assertCode(200)->data();
        $this->assertSame('t-banner', $after['components'][0]['id']);

        $c2 = $this->get('/api/mobile/diy-page', ['key' => 'home'])->assertCode(200)->data();
        $this->assertSame('t-banner', $c2['components'][0]['id'], '回滚不得自动再发');

        $this->get('/api/mobile/diy-page')->assertCode(400);
        $this->get('/api/mobile/diy-page', ['key' => 'not-exist'])->assertCode(404);

        $nobody = $this->actingAsAdmin([]);
        $this->put('/adminapi/diy/home', ['components' => [], 'page_settings' => []], $nobody->token)->assertCode(403);

        $this->restoreHomeSeed();
    }

    private function restoreHomeSeed(): void
    {
        if ($this->homeSeed === null) {
            return;
        }
        Db::table('diy_page_versions')->where('page_id', $this->homeSeed['id'])->delete();
        Db::table('diy_pages')->where('id', $this->homeSeed['id'])->update([
            'components_draft'     => $this->homeSeed['components_draft'],
            'components_published' => $this->homeSeed['components_published'],
            'page_settings'        => $this->homeSeed['page_settings'],
        ]);
    }
}
