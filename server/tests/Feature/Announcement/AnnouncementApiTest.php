<?php

declare(strict_types=1);

namespace tests\Feature\Announcement;

use tests\Support\ApiTestCase;

final class AnnouncementApiTest extends ApiTestCase
{
    public function test_c_end_lists_published_only_and_admin_can_open_draft(): void
    {
        $admin = $this->actingAsAdmin('super');
        $draft = $this->post('/adminapi/announcement', ['title' => 'd', 'content' => 'x', 'type' => 1, 'status' => 0, 'sort' => 0], $admin->token)->assertOk()->data();
        $pub = $this->post('/adminapi/announcement', ['title' => 'p', 'content' => 'y', 'type' => 2, 'status' => 1, 'sort' => 1], $admin->token)->assertOk()->data();
        $this->track('announcements', (int) $draft['id']);
        $this->track('announcements', (int) $pub['id']);
        $this->assertSame($admin->id, (int) $pub['created_by']);
        $this->assertNotEmpty($pub['publish_at']);

        $this->get('/adminapi/announcement/detail/'.$draft['id'], [], $admin->token)->assertOk();
        $this->get('/api/announcement/detail/'.$draft['id'])->assertCode(404);
        $this->get('/api/announcement/detail/'.$pub['id'])->assertOk();
        $ids = array_column($this->get('/api/announcement/list')->assertOk()->data()['list'], 'id');
        $this->assertContains((int) $pub['id'], $ids);
        $this->assertNotContains((int) $draft['id'], $ids);
    }
}
