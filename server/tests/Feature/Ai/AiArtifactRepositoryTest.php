<?php
// tests/Feature/Ai/AiArtifactRepositoryTest.php（依赖本地 DB 与 ai_artifacts 表，无则自动 skip）
declare(strict_types=1);

namespace tests\Feature\Ai;

use app\repository\ai\AiArtifactRepository;
use tests\TestCase;
use think\facade\Db;

class AiArtifactRepositoryTest extends TestCase
{
    private AiArtifactRepository $repository;
    private string $specId;
    private bool $dbAvailable = false;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            Db::connect()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('no database: ' . $e->getMessage());
        }

        $this->repository = new AiArtifactRepository();
        $this->specId = 'spec_' . bin2hex(random_bytes(8));

        try {
            $this->repository->count(['spec_id' => $this->specId]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('ai_artifacts table unavailable: ' . $e->getMessage());
        }

        $this->dbAvailable = true;
    }

    protected function tearDown(): void
    {
        if ($this->dbAvailable) {
            try {
                Db::name('ai_artifacts')->where('spec_id', $this->specId)->delete();
            } catch (\Throwable $e) {
                // 忽略清理失败，不影响测试结果
            }
        }

        parent::tearDown();
    }

    public function testTransitionSupersedeAndList(): void
    {
        $older = $this->repository->create([
            'spec_id' => $this->specId,
            'stage_id' => 'stage_older',
            'module' => 'demo',
            'title' => 'Demo Older',
            'state' => 'compiled',
        ]);
        $newer = $this->repository->create([
            'spec_id' => $this->specId,
            'stage_id' => 'stage_newer',
            'module' => 'demo',
            'title' => 'Demo Newer',
            'state' => 'compiled',
        ]);

        $olderId = (int) $older['id'];
        $keepId = (int) $newer['id'];

        $affected = $this->repository->supersedeOthers($this->specId, $keepId);
        $this->assertSame(1, $affected);

        $olderRow = $this->repository->find($olderId);
        $keepRow = $this->repository->find($keepId);
        $this->assertSame('superseded', $olderRow['state']);
        $this->assertSame('compiled', $keepRow['state']);

        $affected = $this->repository->transition($keepId, ['compiled'], 'checking');
        $this->assertSame(1, $affected);
        $this->assertSame('checking', $this->repository->find($keepId)['state']);

        $affected = $this->repository->transition($keepId, ['compiled'], 'applied');
        $this->assertSame(0, $affected);
        $this->assertSame('checking', $this->repository->find($keepId)['state']);

        $list = $this->repository->listBySpec($this->specId);
        $this->assertCount(2, $list);
        $this->assertGreaterThan((int) $list[1]['id'], (int) $list[0]['id']);
    }
}
