<?php
declare(strict_types=1);

namespace app\service\system;

use app\repository\ai\AiArtifactRepository;
use core\ai\checks\CheckContext;
use core\ai\checks\CheckRunner;
use core\ai\checks\DdlExecutabilityCheck;
use core\ai\checks\ForbiddenPatternsCheck;
use core\ai\checks\LayerComplianceCheck;
use core\ai\checks\PathConventionCheck;
use core\ai\checks\PhpLintCheck;
use core\ai\checks\RequiredFilesCheck;
use core\ai\checks\RouteLoadingCheck;
use core\ai\FileWriter;
use core\base\Service;
use core\database\SqlRunner;
use core\exception\BusinessException;
use think\facade\Db;

class AiArtifactService extends Service
{
    protected AiArtifactRepository $aiArtifactRepository;

    protected function specsBase(): string
    {
        return rtrim(root_path(), '/') . '/runtime/ai/specs';
    }

    public function findByStage(string $specId, string $stageId): ?array
    {
        $rows = $this->aiArtifactRepository->listBySpec($specId);
        foreach ($rows as $r) {
            if (($r['stage_id'] ?? '') === $stageId) {
                return $r;
            }
        }
        return null;
    }

    public function listBySpec(string $specId): array
    {
        return $this->aiArtifactRepository->listBySpec($specId);
    }

    /** 建 artifact（compiled）并把同 spec 旧的置 superseded */
    public function record(string $specId, string $stageId, string $module, string $title): int
    {
        $row = $this->aiArtifactRepository->create([
            'spec_id'  => $specId,
            'stage_id' => $stageId,
            'module'   => $module,
            'title'    => $title,
            'state'    => 'compiled',
        ]);
        $id = (int) $row['id'];
        $this->aiArtifactRepository->supersedeOthers($specId, $id);
        return $id;
    }

    /** compiled|checked_* → checking → checked_passed|checked_failed */
    public function runChecks(int $artifactId): array
    {
        $art = $this->aiArtifactRepository->find($artifactId);
        if (!$art) {
            throw new BusinessException('artifact 不存在');
        }
        $moved = $this->aiArtifactRepository->transition(
            $artifactId,
            ['compiled', 'checked_passed', 'checked_failed'],
            'checking'
        );
        if ($moved === 0) {
            throw new BusinessException('当前状态不可跑检查：' . ($art['state'] ?? ''));
        }

        $summary = $this->makeRunner()->run($this->buildContext($art));
        $to = $summary['passed'] ? 'checked_passed' : 'checked_failed';
        $this->aiArtifactRepository->transition($artifactId, ['checking'], $to, [
            'check_summary' => $summary,
            'checked_at'    => date('Y-m-d H:i:s'),
        ]);

        return ['artifact_id' => $artifactId, 'state' => $to, 'check_summary' => $summary];
    }

    /** 门禁 apply：仅 checked_passed 且 check_summary.passed */
    public function applyArtifact(int $artifactId, ?string $projectRootOverride = null): array
    {
        $art = $this->aiArtifactRepository->find($artifactId);
        if (!$art) {
            throw new BusinessException('artifact 不存在');
        }
        if (($art['state'] ?? '') !== 'checked_passed') {
            throw new BusinessException('门禁未通过，当前状态：' . ($art['state'] ?? ''));
        }
        $summary = $art['check_summary'] ?? [];
        if (empty($summary['passed'])) {
            throw new BusinessException('检查未通过，禁止应用');
        }

        // 原子认领：仅一个并发调用者能把 checked_passed 抢到 applied，落败者/重复调用直接失败，
        // 绝不会跑到 materialize()（避免重复执行副作用）。
        $claimed = $this->aiArtifactRepository->transition($artifactId, ['checked_passed'], 'applied', [
            'applied_at' => date('Y-m-d H:i:s'),
        ]);
        if ($claimed === 0) {
            throw new BusinessException('并发冲突：该工件已被应用或状态已变更');
        }

        try {
            $written = $this->materialize($art, $projectRootOverride);
        } catch (\Throwable $e) {
            // 回滚认领，保持 checked_passed，记录 error
            $this->aiArtifactRepository->transition($artifactId, ['applied'], 'checked_passed', [
                'error' => mb_substr($e->getMessage(), 0, 500, 'UTF-8'),
            ]);
            throw new BusinessException('应用失败：' . $e->getMessage());
        }

        $this->aiArtifactRepository->update($artifactId, ['applied_files' => $written]);

        return ['applied' => true, 'written' => $written];
    }

    protected function makeRunner(): CheckRunner
    {
        return new CheckRunner([
            new PhpLintCheck(),
            new RequiredFilesCheck(),
            new LayerComplianceCheck(),
            new PathConventionCheck(),
            new ForbiddenPatternsCheck(),
            new DdlExecutabilityCheck(),
            new RouteLoadingCheck(),
        ]);
    }

    protected function buildContext(array $art): CheckContext
    {
        $dir = $this->specsBase() . '/' . $art['spec_id'] . '/' . $art['stage_id'];
        $manifest = json_decode((string) @file_get_contents($dir . '/manifest.json'), true);
        if (!is_array($manifest)) {
            throw new BusinessException('manifest 解析失败');
        }
        $schemaPatch = (string) @file_get_contents($dir . '/schema_patch.sql');
        $updateSql = (string) @file_get_contents($dir . '/update.sql');
        $spec = json_decode((string) @file_get_contents($this->specsBase() . '/' . $art['spec_id'] . '/ydspec.json'), true);
        $entities = $manifest['entities'] ?? [];
        return new CheckContext($dir, $manifest, $entities, $schemaPatch, $updateSql, is_array($spec) ? $spec : []);
    }

    /** 物化 stage：执行 update.sql（真前缀）+ 写代码文件到项目，返回写入清单 */
    protected function materialize(array $art, ?string $projectRootOverride): array
    {
        $dir = $this->specsBase() . '/' . $art['spec_id'] . '/' . $art['stage_id'];
        $manifest = json_decode((string) @file_get_contents($dir . '/manifest.json'), true);
        if (!is_array($manifest)) {
            throw new BusinessException('manifest 解析失败');
        }

        $ddl = @file_get_contents($dir . '/update.sql');
        if ($ddl === false) {
            throw new BusinessException('update.sql 读取失败：' . $dir . '/update.sql');
        }
        $this->runDdl($ddl);

        $projectRoot = $projectRootOverride !== null ? rtrim($projectRootOverride, '/') : dirname(rtrim(root_path(), '/'));
        $serverRoot  = $projectRootOverride !== null ? $projectRoot . '/server' : rtrim(root_path(), '/');

        $written = [];
        foreach ($manifest['files'] ?? [] as $f) {
            $rel = (string) ($f['path'] ?? '');
            if (!FileWriter::isSafeRelPath($rel)) {
                continue;
            }
            $src = $dir . '/files/' . $rel;
            if (!is_file($src)) {
                continue;
            }
            $target = str_starts_with($rel, 'admin/') ? $projectRoot . '/' . $rel : $serverRoot . '/' . $rel;
            $targetDir = dirname($target);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new BusinessException('目录创建失败：' . $targetDir);
            }
            if (!copy($src, $target)) {
                throw new BusinessException('文件写入失败：' . $target);
            }
            $written[] = $rel;
        }
        return $written;
    }

    protected function runDdl(string $sql): void
    {
        $pdo = Db::connect()->getPdo();
        $prefix = (string) Db::connect()->getConfig('prefix');
        (new SqlRunner($pdo, $prefix))->runSql($sql);
    }
}
