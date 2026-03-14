<?php
declare(strict_types=1);

namespace tests\Feature\Crud;

use tests\TestCase;
use app\service\agreement\AgreementService;
use core\exception\BusinessException;

class AgreementServiceTest extends TestCase
{
    private AgreementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(AgreementService::class);
    }

    public function testCreateAgreement(): void
    {
        $data = [
            'title' => 'User Agreement',
            'code' => 'test_user_agreement_' . time(),
            'content' => 'Agreement content here',
            'status' => 1,
        ];
        $result = $this->service->create($data);
        $this->assertIsArray($result);
        $this->assertEquals('User Agreement', $result['title']);
        $this->assertArrayHasKey('id', $result);
    }

    public function testCreateDuplicateCodeThrows(): void
    {
        $code = 'duplicate_code_' . time();
        $this->service->create(['title' => 'First', 'code' => $code, 'content' => 'Content', 'status' => 1]);

        $this->expectException(BusinessException::class);
        $this->service->create(['title' => 'Second', 'code' => $code, 'content' => 'Content', 'status' => 1]);
    }

    public function testFindByCode(): void
    {
        $code = 'findbycode_' . time();
        $this->service->create(['title' => 'Find Test', 'code' => $code, 'content' => 'Content', 'status' => 1]);

        $result = $this->service->findByCode($code);
        $this->assertIsArray($result);
        $this->assertEquals('Find Test', $result['title']);
        $this->assertEquals($code, $result['code']);
    }

    public function testFindByCodeNonExistent(): void
    {
        $result = $this->service->findByCode('non_existent_code_' . time());
        $this->assertNull($result);
    }

    public function testGetList(): void
    {
        $result = $this->service->getList(['page_no' => 1, 'page_size' => 10]);
        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertIsArray($result['list']);
    }

    public function testGetListWithDefaultPagination(): void
    {
        $result = $this->service->getList([]);
        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('pagination', $result);
    }

    public function testDetail(): void
    {
        $code = 'detail_test_' . time();
        $created = $this->service->create(['title' => 'Detail Test', 'code' => $code, 'content' => 'Content', 'status' => 1]);

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

    public function testUpdateAgreement(): void
    {
        $code = 'update_test_' . time();
        $created = $this->service->create(['title' => 'Before', 'code' => $code, 'content' => 'Content', 'status' => 1]);
        $result = $this->service->update($created['id'], ['title' => 'After']);
        $this->assertTrue($result);

        // Verify the update
        $updated = $this->service->detail($created['id']);
        $this->assertEquals('After', $updated['title']);
    }

    public function testUpdateNonExistentThrows(): void
    {
        $this->expectException(BusinessException::class);
        $this->service->update(999999, ['title' => 'Should Fail']);
    }

    public function testDeleteAgreement(): void
    {
        $code = 'delete_test_' . time();
        $created = $this->service->create(['title' => 'To Delete', 'code' => $code, 'content' => 'Content', 'status' => 0]);
        $result = $this->service->delete($created['id']);
        $this->assertTrue($result);
    }
}
