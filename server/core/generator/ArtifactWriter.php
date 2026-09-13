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
 *
 * 路径：本类信任调用方给的绝对路径，自己不做任何路径校验——校验在上游的
 * GeneratorService::assertNames()（module_name / model_name 是唯一会进入路径的请求字段，
 * 两条正则在那里判过，且 buildArtifacts() 起手就调它）。本类是 final、包内唯一调用点是
 * GeneratorService::generate()、入参是 Service 现拼的绝对路径，在这里再加一道校验收益不高，
 * 但这条信任关系必须写出来：换了调用方就得自己先过 assertNames()。
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
        $dir = dirname($path);
        if (!is_dir($dir)) {
            error_clear_last();
            // 并发下另一个进程可能刚好建好了同一个目录，所以 mkdir 失败后再确认一次
            if (!@mkdir($dir, 0o755, true) && !is_dir($dir)) {
                return ['status' => 'failed', 'reason' => lang('generator.write_failed') . '：' . $this->lastError($dir)];
            }
        }

        // 「只创建新文件，从不修改已有文件」（spec 决策 3）交给内核保证：'x' 模式在文件已存在时
        // 直接失败（O_CREAT|O_EXCL），不会截断也不会覆盖。以前是 file_exists() 检查完再
        // file_put_contents()，两步之间有一个 TOCTOU 窗口——同一秒里另一个进程（或另一个管理员的
        // 并发生成）刚好创建了同名文件，这边就会把它整个覆盖掉，而这正是本类唯一要守住的不变量。
        error_clear_last();
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            // 'x' 失败有两种原因，必须分开：文件已存在是正常流程（skipped），其余（目录不可写、
            // 磁盘满、路径过长）是真实 IO 错误（failed）。合并成一种会让「目录没有写权限」被
            // 报成「文件已存在」，排查方向直接被带偏。
            if (file_exists($path)) {
                return ['status' => 'skipped', 'reason' => lang('generator.file_exists')];
            }

            return ['status' => 'failed', 'reason' => lang('generator.write_failed') . '：' . $this->lastError($path)];
        }

        error_clear_last();
        $written = @fwrite($handle, $content);
        @fclose($handle);
        // 短写（磁盘写满）与彻底失败一样按 failed 报。半个文件不删：spec 决策 11 明确「已写入的
        // 文件不回滚」，删文件比留文件危险，半成品靠响应里逐条的 status 让人看见。
        if ($written === false || $written !== strlen($content)) {
            return ['status' => 'failed', 'reason' => lang('generator.write_failed') . '：' . $this->lastError($path)];
        }

        return ['status' => 'created', 'reason' => ''];
    }

    /**
     * 具体失败原因（spec §10：目录创建失败 / 无写权限要给出具体原因），形状固定成「原因（文件名）」。
     *
     * 以前在 error_get_last() 拿不到东西时直接返回 $path，于是 files[].reason 这一个字段有时是
     * 「Permission denied」、有时是一个服务器绝对路径，前端与 CLI 都只会原样显示。绝对路径不发给
     * 客户端（与 GeneratorService::displayPath() 同一条约定），所以只取 basename()，并把 PHP 错误
     * 信息里可能自带的绝对路径一并换掉——file_put_contents/fopen 的 E_WARNING 文本里就带着它。
     */
    private function lastError(string $path): string
    {
        $error = error_get_last();
        $message = $error === null ? lang('generator.unknown_error') : (string) $error['message'];

        return str_replace([$path, dirname($path)], [basename($path), '…'], $message) . '（' . basename($path) . '）';
    }
}
