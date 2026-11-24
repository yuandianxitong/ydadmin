<?php

declare(strict_types=1);

namespace core\plugin\saga\steps;

use core\exception\BusinessException;
use core\plugin\PluginPackageInstaller;
use core\saga\SagaContext;

/**
 * install saga 第 1 步：SOURCE_ZIP 首装时从备份 zip 解压出 `plugins/<code>/`。
 */
final class ExtractZipStep extends AbstractPluginSagaStep
{
    public function __construct(array $saga, private readonly PluginPackageInstaller $packageInstaller)
    {
        parent::__construct($saga);
    }

    public function name(): string
    {
        return 'ExtractZip';
    }

    /**
     * created_by_this_run 只在本次真正解压创建了目录时置 true，补偿只删这种目录，
     * 不能误删磁盘原有源码或此前已解压过的目录。它写进 ctx 随 step_state 落库，进程被杀后也能安全补偿。
     */
    public function execute(SagaContext $ctx): void
    {
        if ((bool) ($this->payload()['skip_extract'] ?? false)) {
            // 磁盘原有源码或复用已保留目录的重装：目录不是本次创建的，不置 created_by_this_run。
            // 必须重判目录存在：排队期间目录消失的话，后面各步会静默通过，saga 报告成功而磁盘上没有代码。
            if (!is_dir($this->pluginDir())) {
                throw new BusinessException(
                    sprintf(
                        '插件目录在安装排队期间消失：%s（登记这次安装时它还在；'
                        . '前置校验在另一个进程、另一个时刻做的，此处必须重判——'
                        . '不重判就会把"磁盘上什么都没有"装成 ENABLED）',
                        $this->pluginDir()
                    ),
                    409
                );
            }

            return;
        }

        $packagePath = (string) ($this->payload()['package_path'] ?? '');
        // upload 时跑过完整 inspect()，extract() 只做浅层检查；备份 zip 在这之间被换掉就能落进任意代码，所以校验 hash。
        $this->assertPackageIntegrity($packagePath, (string) ($this->payload()['package_hash'] ?? ''));
        $this->packageInstaller->extract($packagePath, $this->pluginDir());
        $ctx->set('created_by_this_run', true);
    }

    /**
     * 删掉本次解压出来的目录：extract() 拒绝解压到已存在的目录，不清理的话下次重试会卡在解压这一步。
     */
    public function compensate(SagaContext $ctx): void
    {
        if ($ctx->get('created_by_this_run') !== true) {
            return;
        }
        $this->rrmdir($this->pluginDir());

        // rrmdir() 删不掉时不出声，事后核对：目录还在就抛出，计入 compensationErrors()。
        if (is_dir($this->pluginDir())) {
            throw new \RuntimeException(
                '本次解压出来的插件目录未能删除，重试安装会卡在"目标目录已存在"：' . $this->pluginDir()
            );
        }
    }

    /**
     * 校验备份 zip 与登记时算下的 package_hash 一致。hash 为空时跳过：磁盘登记的行不备份 zip，历史行也可能没有这一列。
     */
    private function assertPackageIntegrity(string $packagePath, string $expectedHash): void
    {
        if ($expectedHash === '') {
            return;
        }
        if (!is_file($packagePath)) {
            throw new BusinessException("插件包文件不存在：{$packagePath}", 422);
        }
        $actual = hash_file('sha256', $packagePath);
        if ($actual === false || !hash_equals($expectedHash, $actual)) {
            throw new BusinessException(
                "插件包完整性校验失败：{$packagePath} 的 sha256 与登记的 package_hash 不一致，请重新上传",
                422
            );
        }
    }

    /**
     * 递归删除目录。必须先判 is_link()：is_dir() 对指向目录的软链返回 true，会删到插件目录之外。
     */
    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) {
            $p = "$dir/$f";
            is_dir($p) && !is_link($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
