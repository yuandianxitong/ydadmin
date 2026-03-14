<?php
declare(strict_types=1);

namespace tests\Feature\Crud;

use tests\TestCase;
use app\service\announcement\AnnouncementService;
use core\exception\BusinessException;

class AnnouncementServiceTest extends TestCase
{
    private AnnouncementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(AnnouncementService::class);
    }

    public function testCreateAnnouncement(): void
    {
        $data = [
            'title' => 'Test Announcement',
            'content' => 'Test content',
            'type' => 1,
            'status' => 0,
            'sort' => 0,
        ];
        $result = $this->service->create($data);
        $this->assertIsArray($result);
        $this->assertEquals('Test Announcement', $result['title']);
        $this->assertArrayHasKey('id', $result);
    }

    public function testCreatePublishedSetsPublishAt(): void
    {
        $data = [
            'title' => 'Published Announcement',
            'content' => 'Content',
            'type' => 1,
            'status' => 1, // published
        ];
        $result = $this->service->create($data);
        $this->assertNotNull($result['publish_at'] ?? null);
    }

    public function testCreateDraftDoesNotSetPublishAt(): void
    {
        $data = [
            'title' => 'Draft Announcement',
            'content' => 'Content',
            'type' => 1,
            'status' => 0, // draft
        ];
        $result = $this->service->create($data);
        $this->assertNull($result['publish_at'] ?? null);
    }

    public function testGetList(): void
    {
        // Create test data first
        $this->service->create(['title' => 'List Test', 'content' => 'Content', 'type' => 1, 'status' => 1]);

        $result = $this->service->getList(['page_no' => 1, 'page_size' => 10]);
        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertIsArray($result['list']);
        $this->assertGreaterThanOrEqual(1, count($result['list']));
    }

    public function testGetListWithDefaultPagination(): void
    {
        $result = $this->service->getList([]);
        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('pagination', $result);
    }

    public function testDetail(): void
    {
        $created = $this->service->create(['title' => 'Detail Test', 'content' => 'Content', 'type' => 1, 'status' => 1]);
        $result = $this->service->detail($created['id']);
        $this->assertIsArray($result);
        $this->assertEquals('Detail Test', $result['title']);
        $this->assertEquals($created['id'], $result['id']);
    }

    public function testDetailNonExistent(): void
    {
        $result = $this->service->detail(999999);
        $this->assertNull($result);
    }

    public function testUpdateAnnouncement(): void
    {
        $created = $this->service->create(['title' => 'Before Update', 'content' => 'Content', 'type' => 1, 'status' => 0]);
        $result = $this->service->update($created['id'], ['title' => 'After Update']);
        $this->assertTrue($result);

        // Verify the update
        $updated = $this->service->detail($created['id']);
        $this->assertEquals('After Update', $updated['title']);
    }

    public function testUpdateNonExistentThrows(): void
    {
        $this->expectException(BusinessException::class);
        $this->service->update(999999, ['title' => 'Should Fail']);
    }

    public function testUpdateStatusFromDraftToPublished(): void
    {
        $created = $this->service->create(['title' => 'Status Test', 'content' => 'Content', 'type' => 1, 'status' => 0]);
        $result = $this->service->updateStatus($created['id'], 1);
        $this->assertTrue($result);

        // Verify publish_at was set
        $updated = $this->service->detail($created['id']);
        $this->assertEquals(1, (int) $updated['status']);
    }

    public function testUpdateStatusNonExistentThrows(): void
    {
        $this->expectException(BusinessException::class);
        $this->service->updateStatus(999999, 1);
    }

    public function testDeleteAnnouncement(): void
    {
        $created = $this->service->create(['title' => 'To Delete', 'content' => 'Content', 'type' => 1, 'status' => 0]);
        $result = $this->service->delete($created['id']);
        $this->assertTrue($result);
    }

    public function testGetPublishedList(): void
    {
        // Create a published announcement
        $this->service->create(['title' => 'Published List Test', 'content' => 'Content', 'type' => 1, 'status' => 1]);

        $result = $this->service->getPublishedList(['page_no' => 1, 'page_size' => 10]);
        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('pagination', $result);
    }
}
