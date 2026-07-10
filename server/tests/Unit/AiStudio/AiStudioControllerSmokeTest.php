<?php
// server/tests/Unit/AiStudio/AiStudioControllerSmokeTest.php
namespace tests\Unit\AiStudio;

use app\adminapi\controller\v1\system\AiStudioController;
use app\service\system\AiStudioService;
use core\exception\BusinessException;
use tests\TestCase;

/**
 * Controller 独有逻辑单测（不重复 Task1 Service 层已覆盖的部分）：
 * - apply 空 paths 直接拒绝，不落到 Service
 * - gen_type 非法值回落 crud 预设（LAYER_PRESETS 逻辑）
 * - preview/diff 对非法 stage_id 透传 Service 抛出的业务异常（未被 Controller 吞掉）
 *
 * SSE 的 stream() 端点不适合 PHPUnit 直测（exit + header 输出），
 * 用 Step 5 curl 冒烟覆盖。
 */
class AiStudioControllerSmokeTest extends TestCase
{
    private function makeController(): AiStudioController
    {
        return $this->app->make(AiStudioController::class);
    }

    public function testApplyRejectsEmptyPaths(): void
    {
        $this->app->request->withPost([
            'stage_id' => 'stage_' . str_repeat('0', 16),
            'paths'    => [],
        ]);

        $response = $this->makeController()->apply();
        $data = json_decode((string) $response->getContent(), true);

        $this->assertNotEquals(200, $data['code']);
        $this->assertSame('请选择要写入的文件', $data['message']);
    }

    public function testApplyRejectsBlankPaths(): void
    {
        // array_filter 会滤掉空字符串，等效于空数组
        $this->app->request->withPost([
            'stage_id' => 'stage_' . str_repeat('0', 16),
            'paths'    => ['', null],
        ]);

        $response = $this->makeController()->apply();
        $data = json_decode((string) $response->getContent(), true);

        $this->assertNotEquals(200, $data['code']);
        $this->assertSame('请选择要写入的文件', $data['message']);
    }

    public function testPreviewPropagatesBusinessExceptionOnInvalidStageId(): void
    {
        $this->app->request->withPost([
            'stage_id' => '../../etc',
            'path'     => 'x',
        ]);

        $this->expectException(BusinessException::class);
        $this->makeController()->preview();
    }

    public function testDiffPropagatesBusinessExceptionOnMissingStage(): void
    {
        $this->app->request->withPost([
            'stage_id' => 'stage_' . str_repeat('0', 16),
        ]);

        $this->expectException(BusinessException::class);
        $this->makeController()->diff();
    }

    public function testInvalidGenTypeFallsBackToCrudPreset(): void
    {
        $genType = 'not_a_real_gen_type';
        $layers = AiStudioService::LAYER_PRESETS[$genType] ?? AiStudioService::LAYER_PRESETS['crud'];

        $this->assertSame(AiStudioService::LAYER_PRESETS['crud'], $layers);
    }

    public function testKnownGenTypeUsesItsOwnPreset(): void
    {
        $genType = 'api';
        $layers = AiStudioService::LAYER_PRESETS[$genType] ?? AiStudioService::LAYER_PRESETS['crud'];

        $this->assertSame(AiStudioService::LAYER_PRESETS['api'], $layers);
        $this->assertNotSame(AiStudioService::LAYER_PRESETS['crud'], $layers);
    }

    public function testSanitizeErrorInDebugMode(): void
    {
        $this->app->debug(true);
        $controller = $this->makeController();

        $exception = new \RuntimeException('SQL Syntax Error at line 42');
        $reflected = new \ReflectionMethod($controller, 'sanitizeError');
        $reflected->setAccessible(true);

        $result = $reflected->invoke($controller, $exception);

        // In debug mode, should include original exception message
        $this->assertStringContainsString('生成失败：', $result);
        $this->assertStringContainsString('SQL Syntax Error', $result);
    }

    public function testSanitizeErrorInProductionMode(): void
    {
        $this->app->debug(false);
        $controller = $this->makeController();

        $exception = new \RuntimeException('SQL Syntax Error at line 42');
        $reflected = new \ReflectionMethod($controller, 'sanitizeError');
        $reflected->setAccessible(true);

        $result = $reflected->invoke($controller, $exception);

        // In production mode, should return generic message without leaking details
        $this->assertSame('生成失败，请稍后重试或联系管理员', $result);
        $this->assertStringNotContainsString('SQL', $result);
        $this->assertStringNotContainsString('Error', $result);
    }
}
