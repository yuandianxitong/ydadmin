<?php

declare(strict_types=1);

namespace tests\Support;

use app\service\system\GeneratorService;

/**
 * 测试专用：把 GeneratorService 落盘用的两个根目录（serverRoot()/repoRoot()，Task 8 特地留出的
 * 可覆写测试注入点）换成调用方指定的临时目录，避免生成器测试往真实的 server/app、仓库 admin/
 * 下写文件——那些正是 phpstan / cs-fixer / check:context 的扫描路径，测试中途崩溃留下的半个
 * 模块会让后续每一轮全量门禁都失败，且不容易第一时间看出是测试留下的残留。
 * stub 目录不受影响，仍然走基类默认的 base_path('core/generator/stubs')。
 *
 * 这是 GeneratorArtifactTest 里 TempRootGeneratorService（固定路径 runtime_path('generator-test')）
 * 的共享版本：机制完全相同（覆写同一对 protected 方法），只是把落盘根目录从编译期常量换成
 * 运行期参数，好让本任务的两个测试类各自用自己的随机临时目录、互不冲突、并发安全。
 */
final class TestableGeneratorService extends GeneratorService
{
    private ?string $overrideServerRoot = null;
    private ?string $overrideRepoRoot = null;

    public function useTempRoots(string $serverRoot, string $repoRoot): void
    {
        $this->overrideServerRoot = $serverRoot;
        $this->overrideRepoRoot = $repoRoot;
    }

    protected function serverRoot(): string
    {
        return $this->overrideServerRoot ?? parent::serverRoot();
    }

    protected function repoRoot(): string
    {
        return $this->overrideRepoRoot ?? parent::repoRoot();
    }
}
