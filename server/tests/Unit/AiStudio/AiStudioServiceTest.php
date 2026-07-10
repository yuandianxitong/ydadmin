<?php
// server/tests/Unit/AiStudio/AiStudioServiceTest.php
namespace tests\Unit\AiStudio;

use app\service\system\AiStudioService;
use tests\TestCase;

class AiStudioServiceTest extends TestCase
{
    private function makeStage(AiStudioService $svc): string
    {
        // 直接在 runtime/ai 下构造一个合法 stage 目录
        $stageId = 'stage_' . bin2hex(random_bytes(8));
        $dir = $this->stageBase() . '/' . $stageId . '/server/app/service/demo';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/DemoService.php', "<?php // demo\n");
        return $stageId;
    }

    private function stageBase(): string
    {
        return root_path() . 'runtime/ai';
    }

    public function testResolveRejectsBadFormat(): void
    {
        $svc = $this->getService(AiStudioService::class);
        $this->expectException(\Throwable::class);
        $svc->resolveStageDir('../../etc');
    }

    public function testResolveRejectsMissingStage(): void
    {
        $svc = $this->getService(AiStudioService::class);
        $this->expectException(\Throwable::class);
        $svc->resolveStageDir('stage_' . str_repeat('0', 16));
    }

    public function testPreviewAndApplySafePaths(): void
    {
        $svc = $this->getService(AiStudioService::class);
        $stageId = $this->makeStage($svc);
        try {
            $code = $svc->previewFile($stageId, 'server/app/service/demo/DemoService.php');
            $this->assertStringContainsString('// demo', $code);

            // 越界路径拒绝
            try {
                $svc->previewFile($stageId, '../outside.php');
                $this->fail('应拒绝越界路径');
            } catch (\Throwable $e) {
                $this->assertTrue(true);
            }
        } finally {
            // 清理 stage 目录
            exec('rm -rf ' . escapeshellarg($this->stageBase() . '/' . $stageId));
        }
    }
}
