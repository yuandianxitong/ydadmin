<?php
declare(strict_types=1);

namespace tests\Feature\Crud;

use tests\TestCase;
use app\service\version\AppVersionService;

class AppVersionServiceTest extends TestCase
{
    private AppVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(AppVersionService::class);
    }

    public function testCreateVersion(): void
    {
        $data = [
            'version' => '1.0.0',
            'version_code' => 100,
            'platform' => 'android',
            'download_url' => 'https://example.com/app-1.0.0.apk',
            'description' => 'Initial release',
            'force_update' => 0,
            'status' => 1,
        ];
        $result = $this->service->create($data);
        $this->assertIsArray($result);
        $this->assertEquals('1.0.0', $result['version']);
        $this->assertEquals(100, $result['version_code']);
        $this->assertArrayHasKey('id', $result);
    }

    public function testGetList(): void
    {
        $this->service->create([
            'version' => '1.0.1',
            'version_code' => 101,
            'platform' => 'android',
            'download_url' => 'https://example.com/app-1.0.1.apk',
            'description' => 'Bug fixes',
            'force_update' => 0,
            'status' => 1,
        ]);

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
        $created = $this->service->create([
            'version' => '2.0.0',
            'version_code' => 200,
            'platform' => 'ios',
            'download_url' => 'https://example.com/app-2.0.0.ipa',
            'description' => 'Major update',
            'force_update' => 1,
            'status' => 1,
        ]);

        $result = $this->service->detail($created['id']);
        $this->assertIsArray($result);
        $this->assertEquals('2.0.0', $result['version']);
        $this->assertEquals('ios', $result['platform']);
        $this->assertEquals($created['id'], $result['id']);
    }

    public function testDetailNonExistent(): void
    {
        $result = $this->service->detail(999999);
        $this->assertNull($result);
    }

    public function testUpdateVersion(): void
    {
        $created = $this->service->create([
            'version' => '3.0.0',
            'version_code' => 300,
            'platform' => 'android',
            'download_url' => 'https://example.com/app-3.0.0.apk',
            'description' => 'Before update',
            'force_update' => 0,
            'status' => 1,
        ]);

        $result = $this->service->update($created['id'], [
            'description' => 'After update',
            'force_update' => 1,
        ]);
        $this->assertTrue($result);

        // Verify the update
        $updated = $this->service->detail($created['id']);
        $this->assertEquals('After update', $updated['description']);
    }

    public function testDeleteVersion(): void
    {
        $created = $this->service->create([
            'version' => '4.0.0',
            'version_code' => 400,
            'platform' => 'android',
            'download_url' => 'https://example.com/app-4.0.0.apk',
            'description' => 'To delete',
            'force_update' => 0,
            'status' => 0,
        ]);

        $result = $this->service->delete($created['id']);
        $this->assertTrue($result);
    }

    public function testCheckUpdateWhenUpdateAvailable(): void
    {
        // Create a version with a high version_code
        $this->service->create([
            'version' => '10.0.0',
            'version_code' => 10000,
            'platform' => 'android',
            'download_url' => 'https://example.com/app-10.0.0.apk',
            'description' => 'Latest version',
            'force_update' => 0,
            'status' => 1,
        ]);

        $result = $this->service->checkUpdate('android', 100);
        $this->assertIsArray($result);
        $this->assertTrue($result['need_update']);
        $this->assertArrayHasKey('version', $result);
        $this->assertArrayHasKey('version_code', $result);
        $this->assertArrayHasKey('download_url', $result);
        $this->assertArrayHasKey('description', $result);
    }

    public function testCheckUpdateWhenNoUpdate(): void
    {
        $result = $this->service->checkUpdate('android', 999999);
        $this->assertIsArray($result);
        $this->assertFalse($result['need_update']);
        $this->assertFalse($result['force_update']);
    }

    public function testCheckUpdateForceUpdate(): void
    {
        $this->service->create([
            'version' => '11.0.0',
            'version_code' => 11000,
            'platform' => 'ios',
            'download_url' => 'https://example.com/app-11.0.0.ipa',
            'description' => 'Critical update',
            'force_update' => 1,
            'status' => 1,
        ]);

        $result = $this->service->checkUpdate('ios', 100);
        $this->assertTrue($result['need_update']);
        $this->assertTrue($result['force_update']);
    }

    public function testCheckUpdateForNonExistentPlatform(): void
    {
        $result = $this->service->checkUpdate('non_existent_platform', 1);
        $this->assertFalse($result['need_update']);
    }
}
