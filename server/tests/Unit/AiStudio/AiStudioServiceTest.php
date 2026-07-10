<?php
// server/tests/Unit/AiStudio/AiStudioServiceTest.php
namespace tests\Unit\AiStudio;

use app\service\system\AiStudioService;
use think\facade\Config;
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

    /**
     * 原 testPreviewAndApplySafePaths 更正命名：apply 正向写入会真实落盘到 projectRoot，
     * 无法安全 mock，故只补 preview 正向 + preview/apply 越界拒绝的负向断言。
     */
    public function testPreviewSafePathsAndApplyRejectsTraversal(): void
    {
        $svc = $this->getService(AiStudioService::class);
        $stageId = $this->makeStage($svc);
        try {
            $code = $svc->previewFile($stageId, 'server/app/service/demo/DemoService.php');
            $this->assertStringContainsString('// demo', $code);

            // 越界路径拒绝（preview）
            try {
                $svc->previewFile($stageId, '../outside.php');
                $this->fail('应拒绝越界路径');
            } catch (\Throwable $e) {
                $this->assertTrue(true);
            }

            // 越界路径拒绝（apply）
            try {
                $svc->apply($stageId, ['../outside.php']);
                $this->fail('apply 应拒绝越界路径');
            } catch (\Throwable $e) {
                $this->assertTrue(true);
            }
        } finally {
            // 清理 stage 目录
            exec('rm -rf ' . escapeshellarg($this->stageBase() . '/' . $stageId));
        }
    }

    public function testDiffStripsAnsiColorCodes(): void
    {
        $svc = $this->getService(AiStudioService::class);
        $reflected = new \ReflectionMethod($svc, 'projectRoot');
        $reflected->setAccessible(true);
        $projectRoot = $reflected->invoke($svc);

        $stageId = 'stage_' . bin2hex(random_bytes(8));
        // projectRoot() 是仓库根目录（server/ 的上一级），server/runtime 目录已存在
        $relPath = 'server/runtime/ai_test_diff_ansi_' . bin2hex(random_bytes(4)) . '.php';
        $existingFile = $projectRoot . '/' . $relPath;
        $stageDir = $this->stageBase() . '/' . $stageId;

        // 项目中已存在的文件 + stage 中同路径不同内容的文件，触发 git diff --color 输出 ANSI 序列
        file_put_contents($existingFile, "<?php // original\n");
        mkdir(dirname($stageDir . '/' . $relPath), 0755, true);
        file_put_contents($stageDir . '/' . $relPath, "<?php // modified\n");

        try {
            $diff = $svc->diff($stageId);
            $this->assertStringNotContainsString("\x1b", $diff);
        } finally {
            @unlink($existingFile);
            exec('rm -rf ' . escapeshellarg($stageDir));
        }
    }

    public function testForwardFeedbackSwallowsConnectionError(): void
    {
        $originalEndpoint = config('ai.endpoint');
        $originalTimeout = config('ai.timeout');
        Config::set(['endpoint' => 'http://127.0.0.1:1', 'timeout' => 2], 'ai');

        try {
            $svc = $this->getService(AiStudioService::class);
            $svc->forwardFeedback('gen_test_id', 'accepted');
            // 未抛出异常即通过：forwardFeedback 内部吞掉连接失败
            $this->assertTrue(true);
        } finally {
            Config::set(['endpoint' => $originalEndpoint, 'timeout' => $originalTimeout], 'ai');
        }
    }
}
