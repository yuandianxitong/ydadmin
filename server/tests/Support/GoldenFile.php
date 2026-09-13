<?php

declare(strict_types=1);

namespace tests\Support;

/**
 * 黄金文件断言（spec §11.1）：生成产物与 tests/fixtures/ 下的期望文件逐字节比对。
 *
 * 夹具树与生成树逐层同构，根是 tests/fixtures/generated/：
 *
 *     产物 app/model/demo/GenArticle.php
 *       → tests/fixtures/generated/app/model/demo/GenArticle.php
 *     产物 admin/src/api/gen-article.ts
 *       → tests/fixtures/generated/admin/src/api/gen-article.ts
 *
 * 所以夹具路径由产物路径直接拼出来，没有 key → 夹具路径的映射表要维护，也就没有「新增产物
 * 忘了同步映射」这种失配。后端产物相对 server/、前端产物相对仓库根，两组顶层目录不相交。
 * 换一套输入（如 override 变体）就换一个根（$tree），树形与路径保持不变。
 *
 * 首次生成期望文件、或改了模板要更新期望文件：
 *
 *     YDADMIN_UPDATE_GOLDEN=1 vendor/bin/phpunit --filter GoldenModuleTest
 *
 * 更新模式把渲染结果写回期望文件后**必定让用例失败**——它永远不会变绿，因此既不可能被
 * 塞进 CI，也不可能在日常流程里被顺手带过。写完先 `git diff server/tests/fixtures`
 * 审一遍，确认每一处改动都是有意为之，再去掉环境变量重跑，绿了才算数。
 *
 * 期望文件必须与模板在同一个 commit 里提交：模板的每一次改动都要在 diff 里看得见。
 */
trait GoldenFile
{
    protected static function goldenRoot(string $tree = 'generated'): string
    {
        return dirname(__DIR__) . '/fixtures/' . $tree;
    }

    /**
     * @param string $relativePath 产物相对其基准根的路径，如 app/model/demo/GenArticle.php
     * @param string $tree         夹具根目录名：默认输入用 generated，变体输入用 generated-xxx
     */
    protected function assertGoldenFile(string $relativePath, string $content, string $tree = 'generated'): void
    {
        $this->assertMatchesRegularExpression('/^generated(-[a-z0-9]+)*$/', $tree, '夹具根只能是 generated 或 generated-xxx');
        $this->assertMatchesRegularExpression('#^[A-Za-z0-9][A-Za-z0-9_./-]*$#', $relativePath, '产物路径必须是相对路径');
        $this->assertStringNotContainsString('..', $relativePath, '产物路径不得包含 ..');

        $path = self::goldenRoot($tree) . '/' . $relativePath;
        $relative = "server/tests/fixtures/{$tree}/{$relativePath}";

        if (getenv('YDADMIN_UPDATE_GOLDEN') === '1') {
            $this->assertFalse(
                getenv('CI') !== false,
                '更新模式不允许在 CI 里跑：期望文件必须由人在本地生成并审过 diff 再提交',
            );
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0o755, true);
            }
            file_put_contents($path, $content);
            self::fail("已写入期望文件 {$relative}（" . strlen($content) . " 字节）。"
                . "请 git diff 审阅后，去掉 YDADMIN_UPDATE_GOLDEN 重跑——更新模式永远不会通过。");
        }

        $this->assertFileExists($path, "缺少期望文件 {$relative}："
            . '首次生成请跑 YDADMIN_UPDATE_GOLDEN=1 vendor/bin/phpunit --filter GoldenModuleTest');
        $this->assertSame(
            (string) file_get_contents($path),
            $content,
            "生成产物与期望文件 {$relative} 不一致。改模板是有意的就用 YDADMIN_UPDATE_GOLDEN=1 重新生成并审 diff；"
            . '不是有意的就说明模板被改坏了。',
        );
    }
}
