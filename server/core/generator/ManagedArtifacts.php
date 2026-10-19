<?php

declare(strict_types=1);

namespace core\generator;

use core\support\PathGuard;

/** 生成基线独立于业务源码；只信实时渲染得到的目标路径，不从状态文件读取写入目的地。 */
final class ManagedArtifacts
{
    private readonly string $statePath;
    private readonly string $directory;

    public function __construct(private readonly string $serverRoot, private readonly string $repoRoot, private readonly GeneratorRequest $request)
    {
        $this->directory = $serverRoot . '/generator-state/' . $request->moduleName . '/' . (new NameConvention())->snake($request->modelName);
        $this->statePath = $this->directory . '/baseline.json';
        PathGuard::within($serverRoot, $this->statePath);
    }

    /** 增量更新沿用上次保存的字段开关；本次显式传入的开关优先。 */
    public function defaults(): GeneratorRequest
    {
        $options = $this->state([])['options'];
        return new GeneratorRequest(
            $this->request->tableName,
            $this->request->moduleName,
            $this->request->modelName,
            $this->request->tableComment !== '' ? $this->request->tableComment : $options['table_comment'],
            $this->request->overrides + $options['overrides']
        );
    }

    /** 从调用方提供的原始生成快照导入基线；不会从当前手改文件猜测原始内容。
     * @param array<string, array{path: string, display: string, content: string}> $files
     * @return array{imported: int} */
    public function importBaseline(array $files, string $snapshotRoot): array
    {
        $root = realpath($snapshotRoot);
        if ($root === false || !is_dir($root) || $root === realpath($this->repoRoot)) {
            throw new \RuntimeException('请提供独立的原始生成快照根目录');
        }
        return $this->locked(function () use ($files, $root): array {
            $state = $this->state($files);
            if ($state['artifacts'] !== []) {
                throw new \RuntimeException('模块已有基线，不能重新导入');
            }
            // 快照若携带生成基线，沿用其中的原始选项；纯源码快照使用调用方明确提供的选项。
            $original = (new self($root . '/server', $root, $this->request))->state($files);
            $state['options'] = [
                'table_comment' => $this->request->tableComment !== '' ? $this->request->tableComment : $original['options']['table_comment'],
                'overrides' => $this->request->overrides + $original['options']['overrides'],
            ];
            foreach ($files as $key => $file) {
                $path = $root . '/' . $file['display'];
                PathGuard::within($root, $path);
                if (!is_file($path)) {
                    continue;
                }
                $size = filesize($path);
                $content = $size !== false && $size <= 2 * 1024 * 1024 ? @file_get_contents($path) : false;
                if ($content === false) {
                    throw new \RuntimeException('原始快照文件无法读取或超过大小限制：' . $file['display']);
                }
                if (str_ends_with($path, '.php') && token_get_all($content, TOKEN_PARSE) === []) {
                    throw new \RuntimeException('原始 PHP 快照不能为空');
                }
                $state['artifacts'][$key] = $this->baseline(['path' => $file['path'], 'display' => $file['display'], 'content' => $content]);
            }
            if (!isset($state['artifacts']['controller'])) {
                throw new \RuntimeException('原始快照缺少该模块的控制器');
            }
            $this->save($state);
            return ['imported' => count($state['artifacts'])];
        });
    }

    /** @param array<string, array{path: string, display: string, content: string}> $files
     * @return array<string, array{status: string, reason: string}> */
    public function create(array $files): array
    {
        return $this->locked(function () use ($files): array {
            $state = $this->state($files);
            $statuses = (new ArtifactWriter())->write($files);
            foreach ($files as $key => $file) {
                if (in_array($statuses[$key]['status'], ['created', 'skipped'], true) && $this->read($file['path']) === $file['content']) {
                    $state['artifacts'][$key] = $this->baseline($file);
                }
            }
            $this->save($state);
            return $statuses;
        });
    }

    /** @param array<string, array{path: string, display: string, content: string}> $files
     * @return array<string, mixed> */
    public function plan(array $files): array
    {
        $state = $this->state($files);
        $result = [];
        $fingerprints = [];
        $conflicts = false;
        foreach ($files as $key => $file) {
            $current = $this->read($file['path']);
            $previous = $state['artifacts'][$key] ?? null;
            $base = $previous['content'] ?? null;
            $reason = '';
            $content = $file['content'];
            if ($previous !== null && $previous['path'] !== $file['display']) {
                $status = 'conflict';
                $reason = lang('generator.path_changed');
            } elseif ($current === null) {
                $status = $previous === null ? 'created' : 'conflict';
                $reason = $previous === null ? '' : lang('generator.generated_file_deleted');
            } elseif ($current === $file['content']) {
                $status = 'unchanged';
            } elseif ($base === null) {
                $status = 'conflict';
                $reason = lang('generator.baseline_missing');
            } else {
                $merged = (new ThreeWayMerge())->merge($base, $current, $file['content']);
                if ($merged === null) {
                    $status = 'conflict';
                    $reason = lang('generator.merge_conflict');
                } else {
                    $content = $merged;
                    $status = $content === $current ? 'preserved' : ($current === $base ? 'updated' : 'merged');
                }
            }
            if ($status !== 'conflict') {
                try {
                    if (str_ends_with($file['path'], '.php')) {
                        if (token_get_all($content, TOKEN_PARSE) === []) {
                            throw new \RuntimeException('PHP 产物不能为空');
                        }
                    }
                } catch (\Throwable) {
                    $status = 'conflict';
                    $reason = lang('generator.invalid_merge');
                }
            }
            $conflicts = $conflicts || $status === 'conflict';
            $entry = ['key' => $key, 'path' => $file['display'], 'status' => $status, 'reason' => $reason,
                'current_hash' => $current === null ? null : hash('sha256', $current), 'content' => $content];
            if ($status === 'conflict') {
                $entry += ['base_content' => $base, 'current_content' => $current, 'incoming_content' => $file['content']];
            }
            $result[] = $entry;
            $fingerprints[$key] = [$file['display'], $entry['current_hash'], hash('sha256', $file['content'])];
        }
        return ['files' => $result, 'has_conflicts' => $conflicts,
            'plan_token' => hash('sha256', json_encode([$state, $fingerprints], JSON_THROW_ON_ERROR))];
    }

    /** @param array<string, array{path: string, display: string, content: string}> $files
     * @return array<string, mixed> */
    public function update(array $files, string $expectedToken): array
    {
        return $this->locked(function () use ($files, $expectedToken): array {
            $plan = $this->plan($files);
            if (!hash_equals($plan['plan_token'], $expectedToken)) {
                throw new \core\exception\BusinessException(lang('generator.plan_expired'), 409);
            }
            if ($plan['has_conflicts']) {
                $folder = $this->directory . '/conflicts/' . substr($expectedToken, 0, 16);
                foreach ($plan['files'] as $entry) {
                    if ($entry['status'] !== 'conflict') {
                        continue;
                    }
                    foreach (['base', 'current', 'incoming'] as $version) {
                        $this->atomic($folder . '/' . $entry['key'] . '.' . $version . '.txt', (string) ($entry[$version . '_content'] ?? ''), 0o600);
                    }
                }
                return $plan + ['applied' => false, 'conflict_directory' => $this->display($folder)];
            }
            $state = $this->state($files);
            $backup = $this->directory . '/backups/' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
            $state['options'] = ['table_comment' => $this->request->tableComment, 'overrides' => $this->request->overrides];
            // 控制器最后更新；一旦发生 IO 错误停止后续写入，已更新文件可从备份恢复。
            $entries = $plan['files'];
            usort($entries, static fn (array $a, array $b): int => ($a['key'] === 'controller') <=> ($b['key'] === 'controller'));
            foreach ($entries as $entry) {
                $file = $files[$entry['key']];
                $current = $this->read($file['path']);
                if (($current === null ? null : hash('sha256', $current)) !== $entry['current_hash']) {
                    throw new \core\exception\BusinessException(lang('generator.source_changed'), 409);
                }
                if (in_array($entry['status'], ['created', 'updated', 'merged'], true)) {
                    if ($current !== null) {
                        $this->atomic($backup . '/' . $entry['key'] . '.txt', $current, 0o600);
                        $this->atomic($file['path'], $entry['content'], fileperms($file['path']) & 0o777);
                    } else {
                        $written = (new ArtifactWriter())->write([$entry['key'] => $file]);
                        if ($written[$entry['key']]['status'] !== 'created') {
                            throw new \RuntimeException('新产物创建失败，停止更新：' . $file['display']);
                        }
                    }
                }
                // 保存的是纯生成版本，不能把保留下来的自定义代码变成下一次的生成基线。
                $state['artifacts'][$entry['key']] = $this->baseline($file);
                $this->save($state);
            }
            return $plan + ['applied' => true, 'backup_directory' => $this->display($backup)];
        });
    }

    /** --force 仅供 CLI 显式使用，保留页面、表单并在替换其他已有产物前备份。
     * @param array<string, array{path: string, display: string, content: string}> $files
     * @return array<string, array{status: string, reason: string}> */
    public function force(array $files): array
    {
        return $this->locked(function () use ($files): array {
            $state = $this->state($files);
            $result = [];
            $state['options'] = ['table_comment' => $this->request->tableComment, 'overrides' => $this->request->overrides];
            $backup = $this->directory . '/backups/' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
            $order = array_keys($files);
            usort($order, static fn (string $a, string $b): int => ($a === 'controller') <=> ($b === 'controller'));
            foreach ($order as $key) {
                $file = $files[$key];
                $current = $this->read($file['path']);
                if ($current !== null && in_array($key, ['page', 'form'], true)) {
                    $result[$key] = ['status' => 'skipped', 'reason' => lang('generator.frontend_preserved')];
                    continue;
                }
                if ($current !== null) {
                    $this->atomic($backup . '/' . $key . '.txt', $current, 0o600);
                    $this->atomic($file['path'], $file['content'], fileperms($file['path']) & 0o777);
                    $result[$key] = ['status' => 'updated', 'reason' => lang('generator.backup') . ': ' . $this->display($backup . '/' . $key . '.txt')];
                } else {
                    $result[$key] = (new ArtifactWriter())->write([$key => $file])[$key];
                }
                if (in_array($result[$key]['status'], ['created', 'updated'], true)) {
                    $state['artifacts'][$key] = $this->baseline($file);
                    $this->save($state);
                }
            }
            return array_replace(array_fill_keys(array_keys($files), []), $result);
        });
    }

    /** @param array<string, array{path: string, display: string, content: string}> $files
     * @return array{format_version: int, identity: array<string, string>, options: array{table_comment: string, overrides: array<string, array{form_type: string, searchable: bool, in_list: bool, in_form: bool, options?: list<array{label: string, value: string}>}>}, artifacts: array<string, array{path: string, content: string, sha256: string}>} */
    private function state(array $files): array
    {
        $identity = ['table' => $this->request->tableName, 'module' => $this->request->moduleName, 'model' => $this->request->modelName];
        $text = $this->read($this->statePath, 32 * 1024 * 1024);
        if ($text === null) {
            return ['format_version' => 1, 'identity' => $identity, 'options' => ['table_comment' => $this->request->tableComment, 'overrides' => $this->request->overrides], 'artifacts' => []];
        }
        $state = json_decode($text, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($state) || ($state['format_version'] ?? null) !== 1 || ($state['identity'] ?? null) !== $identity || !is_array($state['artifacts'] ?? null)
            || !is_string($state['options']['table_comment'] ?? null) || !is_array($state['options']['overrides'] ?? null)) {
            throw new \RuntimeException('生成基线身份不一致或格式损坏');
        }
        // 只管理当前蓝图中的产物；旧版本废弃的基线项在下次成功写入时清理。
        $state['artifacts'] = array_intersect_key($state['artifacts'], $files);
        foreach ($state['options']['overrides'] as $option) {
            if (!is_array($option) || !is_string($option['form_type'] ?? null) || !is_bool($option['searchable'] ?? null)
                || !is_bool($option['in_list'] ?? null) || !is_bool($option['in_form'] ?? null)) {
                throw new \RuntimeException('生成字段选项损坏');
            }
        }
        foreach ($state['artifacts'] as $entry) {
            if (!is_array($entry) || !is_string($entry['path'] ?? null) || !is_string($entry['content'] ?? null)
                || ($entry['sha256'] ?? null) !== hash('sha256', $entry['content'])) {
                throw new \RuntimeException('生成基线校验失败');
            }
        }
        return $state;
    }

    /** @param array{path: string, display: string, content: string} $file
     * @return array{path: string, content: string, sha256: string} */
    private function baseline(array $file): array
    {
        return ['path' => $file['display'], 'content' => $file['content'], 'sha256' => hash('sha256', $file['content'])];
    }

    /** @param array<string, mixed> $state */
    private function save(array $state): void
    {
        $this->atomic($this->statePath, json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", 0o600);
    }

    private function read(string $path, int $limit = 2 * 1024 * 1024): ?string
    {
        PathGuard::within(str_starts_with($path, $this->serverRoot . '/') ? $this->serverRoot : $this->repoRoot, $path);
        clearstatcache(true, $path);
        if (is_link($path)) {
            throw new \RuntimeException('不允许读取符号链接产物');
        }
        if (!file_exists($path)) {
            return null;
        }
        if (!is_file($path) || filesize($path) > $limit || ($content = @file_get_contents($path)) === false) {
            throw new \RuntimeException('产物无法读取或超过大小限制：' . basename($path));
        }
        return $content;
    }

    private function mkdir(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new \RuntimeException('生成目录不可写');
        }
    }

    private function atomic(string $path, string $content, int $mode): void
    {
        PathGuard::within(str_starts_with($path, $this->serverRoot . '/') ? $this->serverRoot : $this->repoRoot, $path);
        $this->mkdir(dirname($path));
        $temp = tempnam(dirname($path), '.generator-');
        if ($temp === false) {
            throw new \RuntimeException('无法创建生成临时文件');
        }
        try {
            if (file_put_contents($temp, $content, LOCK_EX) !== strlen($content) || !chmod($temp, $mode) || !rename($temp, $path)) {
                throw new \RuntimeException('生成文件替换失败：' . basename($path));
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }

    /** @template T
     * @param \Closure(): T $callback
     * @return T */
    private function locked(\Closure $callback): mixed
    {
        PathGuard::within($this->serverRoot, $this->directory . '/module.lock');
        $this->mkdir($this->directory);
        $lock = fopen($this->directory . '/module.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException('无法创建生成锁');
        }
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                throw new \core\exception\BusinessException(lang('generator.module_busy'), 409);
            }
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function display(string $path): string
    {
        return 'server/' . substr($path, strlen($this->serverRoot) + 1);
    }
}
