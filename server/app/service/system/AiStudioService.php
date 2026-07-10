<?php
// server/app/service/system/AiStudioService.php
declare(strict_types=1);

namespace app\service\system;

use core\ai\AiClient;
use core\ai\DiffPreview;
use core\ai\FileWriter;
use core\ai\SchemaReader;
use core\ai\YdConfig;
use core\base\Service;
use core\exception\BusinessException;

/**
 * AI Studio 后台工作台编排：生成 → 暂存 stage → 预览/diff → 选择写入
 * 全部 AI 能力复用 core/ai，本类只做 stage 生命周期与安全边界
 */
class AiStudioService extends Service
{
    public const MAX_TABLES = 5;
    public const MAX_INSTRUCTION = 500;
    public const LAYER_PRESETS = [
        'crud'    => ['controller', 'service', 'repository', 'model'],
        'feature' => ['controller', 'service', 'repository', 'model'],
        'api'     => ['controller', 'service', 'repository'],
    ];

    protected function projectRoot(): string
    {
        return realpath(root_path() . '/..') ?: dirname(root_path());
    }

    protected function stageBase(): string
    {
        return rtrim(root_path(), '/') . '/runtime/ai';
    }

    protected function makeClient(): AiClient
    {
        $token = env('YD_AI_TOKEN') ?: (new YdConfig())->get('token');
        return new AiClient((string) config('ai.endpoint'), $token ?: null, (int) config('ai.timeout'));
    }

    public function generateToStage(string $instruction, array $tables, array $layers, ?callable $onChunk = null): array
    {
        (new FileWriter($this->projectRoot()))->cleanupStale();

        $schemaInput = (new SchemaReader())->buildSchemaInput($tables);
        $projectId = 'studio_' . substr(md5($this->projectRoot()), 0, 8);
        $result = $this->makeClient()->generate($instruction, $projectId, $layers, $schemaInput, $onChunk);

        $stageId = 'stage_' . bin2hex(random_bytes(8));
        $stageDir = $this->stageBase() . '/' . $stageId;
        $fileMeta = [];
        $skipped = [];
        foreach ($result['files'] ?? [] as $file) {
            if (!FileWriter::isSafeRelPath($file['path'])) {
                $skipped[] = $file['path'];
                continue;
            }
            $target = $stageDir . '/' . $file['path'];
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            file_put_contents($target, $file['code']);
            $fileMeta[] = [
                'path'   => $file['path'],
                'lines'  => substr_count($file['code'], "\n") + 1,
                'exists' => is_file($this->projectRoot() . '/' . $file['path']),
            ];
        }
        return [
            'stage_id'      => $stageId,
            'generation_id' => (string) ($result['generation_id'] ?? ''),
            'files'         => $fileMeta,
            'skipped'       => $skipped,
        ];
    }

    public function resolveStageDir(string $stageId): string
    {
        if (!preg_match('/^stage_[0-9a-f]{16}$/', $stageId)) {
            throw new BusinessException('无效的 stage_id');
        }
        $dir = $this->stageBase() . '/' . $stageId;
        if (!is_dir($dir)) {
            throw new BusinessException('生成结果已过期或不存在，请重新生成');
        }
        return $dir;
    }

    protected function safeStageFile(string $stageId, string $path): string
    {
        if (!FileWriter::isSafeRelPath($path)) {
            throw new BusinessException('非法文件路径');
        }
        $stageDir = $this->resolveStageDir($stageId);
        $file = $stageDir . '/' . $path;
        $real = realpath($file);
        if ($real === false || !str_starts_with($real, realpath($stageDir) . '/')) {
            throw new BusinessException('文件不存在或越界');
        }
        return $real;
    }

    public function previewFile(string $stageId, string $path): string
    {
        return (string) file_get_contents($this->safeStageFile($stageId, $path));
    }

    /** 读 stage 目录全部文件重组 files 数组 */
    protected function stageFiles(string $stageId): array
    {
        $stageDir = $this->resolveStageDir($stageId);
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($stageDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $rel = ltrim(substr($item->getPathname(), strlen($stageDir)), '/');
                $files[] = ['path' => $rel, 'code' => (string) file_get_contents($item->getPathname())];
            }
        }
        return $files;
    }

    public function diff(string $stageId): string
    {
        $stageDir = $this->resolveStageDir($stageId);
        $text = (new DiffPreview($this->projectRoot()))->render($stageDir, $this->stageFiles($stageId));
        // git diff --color 会输出 ANSI 转义序列，前端纯文本展示前需剥离
        return preg_replace('/\x1b\[[0-9;]*m/', '', $text);
    }

    public function apply(string $stageId, array $paths): array
    {
        $written = [];
        foreach ($paths as $path) {
            $src = $this->safeStageFile($stageId, $path);
            $target = $this->projectRoot() . '/' . $path;
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            copy($src, $target);
            $written[] = $path;
        }
        return $written;
    }

    public function forwardFeedback(string $generationId, string $action): void
    {
        if ($generationId === '' || !in_array($action, ['accepted', 'rejected'], true)) {
            return;
        }
        try {
            $this->makeClient()->feedback($generationId, $action);
        } catch (\Throwable $e) {
            trace('AI Studio 反馈转发失败（已忽略）：' . $e->getMessage(), 'debug');
        }
    }

    public function cleanupStale(): void
    {
        (new FileWriter($this->projectRoot()))->cleanupStale();
    }
}
