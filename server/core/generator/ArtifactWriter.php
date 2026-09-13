<?php

declare(strict_types=1);

namespace core\generator;

/**
 * 产物落盘器（spec §10）。只接受「已经全部渲染成功」的内容，自己不渲染——两阶段的第一阶段在
 * GeneratorService::buildArtifacts() 里完成，任一产物渲染失败就整批不进这里。
 *
 * 三条硬规则：
 *   1. 路由文件最后写。路由已注册而控制器还没落盘，进来的请求直接 500（spec 决策 10）。
 *   2. 单个文件失败不中断整批，逐条记状态（与 M1c 批量删除同一语义）。
 *   3. 已写入的文件不回滚（spec 决策 11）：删文件比留文件危险，半成品靠响应里的逐条 status 让人看见。
 *
 * 所有文件系统调用都带 @ 并检查返回值：phpunit.xml 开了 failOnWarning，一个 E_WARNING 就会让测试失败，
 * 而「目标目录不可写」恰恰是本类要正常处理并转成 failed 状态的情况。
 */
final class ArtifactWriter
{
    /** 最后写的产物 key（spec §10 落盘顺序）。 */
    private const LAST = 'route';

    /**
     * @param array<string, array{path: string, content: string}> $files key → 绝对路径与内容
     * @return array<string, array{status: string, reason: string}> key → 状态，顺序与入参一致
     */
    public function write(array $files): array
    {
        $statuses = [];
        foreach ($this->writeOrder($files) as $key) {
            $statuses[$key] = $this->writeOne($files[$key]['path'], $files[$key]['content']);
        }

        // 回到入参顺序（= 底稿 §4 的产物顺序 = 前端页签顺序），落盘顺序只是内部实现
        $ordered = [];
        foreach ($files as $key => $_) {
            $ordered[$key] = $statuses[$key];
        }

        return $ordered;
    }

    /**
     * 落盘顺序：路由排最后，其余保持原序。
     *
     * @param array<string, array{path: string, content: string}> $files
     * @return list<string>
     */
    private function writeOrder(array $files): array
    {
        $keys = array_keys($files);
        $rest = array_values(array_filter($keys, static fn (string $key): bool => $key !== self::LAST));

        return in_array(self::LAST, $keys, true) ? [...$rest, self::LAST] : $rest;
    }

    /** @return array{status: string, reason: string} */
    private function writeOne(string $path, string $content): array
    {
        // 生成器只创建新文件，从不修改已有文件（spec 决策 3）
        if (file_exists($path)) {
            return ['status' => 'skipped', 'reason' => lang('generator.file_exists')];
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            error_clear_last();
            // 并发下另一个进程可能刚好建好了同一个目录，所以 mkdir 失败后再确认一次
            if (!@mkdir($dir, 0o755, true) && !is_dir($dir)) {
                return ['status' => 'failed', 'reason' => lang('generator.write_failed') . '：' . $this->lastError($dir)];
            }
        }

        error_clear_last();
        if (@file_put_contents($path, $content) === false) {
            return ['status' => 'failed', 'reason' => lang('generator.write_failed') . '：' . $this->lastError($path)];
        }

        return ['status' => 'created', 'reason' => ''];
    }

    /** 具体失败原因（spec §10：目录创建失败 / 无写权限要给出具体原因）。 */
    private function lastError(string $path): string
    {
        $error = error_get_last();

        return $error === null ? $path : (string) $error['message'];
    }
}
