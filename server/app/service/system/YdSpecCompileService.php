<?php
declare(strict_types=1);

namespace app\service\system;

use core\ai\FileWriter;
use core\ai\ydspec\YdSpecCompiler;
use core\ai\ydspec\YdSpecValidator;
use core\base\Service;
use core\exception\BusinessException;

class YdSpecCompileService extends Service
{
    protected YdSpecValidator $ydSpecValidator;
    protected AiArtifactService $aiArtifactService;

    protected function specsBase(): string
    {
        return rtrim(root_path(), '/') . '/runtime/ai/specs';
    }

    protected function loadSpec(string $specId): array
    {
        if (!preg_match('/^spec_[0-9a-f]{16}$/', $specId)) {
            throw new BusinessException('非法的 spec_id');
        }
        $file = $this->specsBase() . '/' . $specId . '/ydspec.json';
        if (!is_file($file)) {
            throw new BusinessException('规格不存在：' . $specId);
        }
        $spec = json_decode((string) file_get_contents($file), true);
        if (!is_array($spec)) {
            throw new BusinessException('规格文件解析失败');
        }
        return $spec;
    }

    /**
     * 编译并落 stage，返回预览信息
     */
    public function compile(string $specId): array
    {
        $spec = $this->loadSpec($specId);

        $errors = $this->ydSpecValidator->validateStructure($spec);
        if ($errors) {
            throw new BusinessException('规格结构校验未通过：' . implode('；', $errors));
        }
        $blocking = array_filter(
            $this->ydSpecValidator->validateSemantics($spec),
            static fn (array $i): bool => $i['severity'] === 'error'
        );
        if ($blocking) {
            throw new BusinessException('规格语义校验未通过：' . implode('；', array_column($blocking, 'message')));
        }

        $compiled = (new YdSpecCompiler())->compile($spec);

        $codeGen = new CodeGeneratorService();
        $codeFiles = [];
        foreach ($compiled['entities'] as $ent) {
            $files = $codeGen->generate([
                'table_name'    => $ent['table'],
                'module_name'   => $ent['module'],
                'model_name'    => $ent['model'],
                'table_comment' => $ent['comment'],
                'columns'       => $ent['columns'],
            ]);
            foreach ($files as $key => $file) {
                if ($key === 'route') {
                    // 路由单独整合（单层分组 + 中间件），避免多实体互相覆盖及双层嵌套
                    continue;
                }
                $codeFiles[$file['path']] = $file['content'];
            }
        }
        $module = (string) $spec['module']['name'];
        $codeFiles["app/adminapi/route/{$module}.php"] = $this->routeFileContent($compiled['entities']);

        // 原子占位：父目录 specs/<specId> 已存在（loadSpec 已校验），非递归 mkdir 具原子性，
        // 目录已存在时返回 false，从而保证「每次新建 stage 不覆盖旧的」。
        $stageId = '';
        $dir = '';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = 'compile_' . bin2hex(random_bytes(8));
            $candidateDir = $this->specsBase() . '/' . $specId . '/' . $candidate;
            if (@mkdir($candidateDir, 0755, false)) {
                $stageId = $candidate;
                $dir = $candidateDir;
                break;
            }
        }
        if ($dir === '') {
            throw new BusinessException('stage 目录创建失败（多次冲突）');
        }
        $this->writeFile($dir . '/schema_patch.sql', $compiled['schema_patch']);
        $this->writeFile($dir . '/update.sql', $compiled['update_sql']);

        $manifestFiles = [];
        foreach ($codeFiles as $path => $content) {
            if (!FileWriter::isSafeRelPath($path)) {
                continue;
            }
            $this->writeFile($dir . '/files/' . $path, $content);
            $manifestFiles[] = ['path' => $path, 'bytes' => strlen($content)];
        }

        // 供检查库使用的实体元数据（精简，不含 columns 大块）
        $entitiesMeta = [];
        foreach ($compiled['entities'] as $ent) {
            $entitiesMeta[] = [
                'name'              => $ent['name'],
                'table'             => $ent['table'],
                'module'            => $ent['module'],
                'model'             => $ent['model'],
                'route_group'       => $ent['route_group'],
                'is_main'           => $ent['is_main'],
                'has_status_switch' => $ent['has_status_switch'],
            ];
        }

        $manifest = [
            'spec_id'      => $specId,
            'stage_id'     => $stageId,
            'created_at'   => date('Y-m-d H:i:s'),
            'schema_patch' => 'schema_patch.sql',
            'update_sql'   => 'update.sql',
            'entities'     => $entitiesMeta,
            'files'        => $manifestFiles,
        ];
        $this->writeFile(
            $dir . '/manifest.json',
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        // 登记 artifact + 自动跑检查（状态机）
        $module = (string) ($spec['module']['name'] ?? '');
        $title  = (string) ($spec['module']['title'] ?? $module);
        $artifactId = $this->aiArtifactService->record($specId, $stageId, $module, $title);
        $checked = $this->aiArtifactService->runChecks($artifactId);

        return [
            'artifact_id'  => $artifactId,
            'stage_id'     => $stageId,
            'dir'          => 'runtime/ai/specs/' . $specId . '/' . $stageId,
            'schema_patch' => $compiled['schema_patch'],
            'update_sql'   => $compiled['update_sql'],
            'files'        => $manifestFiles,
            'check_summary' => $checked['check_summary'],
        ];
    }

    /**
     * 整合路由文件：每实体一个单层分组，各自挂中间件（符合 AGENTS.md）。
     * @param array<int,array{module:string,model:string,route_group:string,has_status_switch:bool}> $entities
     */
    protected function routeFileContent(array $entities): string
    {
        $groups = [];
        foreach ($entities as $ent) {
            $ctrl   = "v1.{$ent['module']}.{$ent['model']}Controller";
            $group  = $ent['route_group'];
            $status = !empty($ent['has_status_switch'])
                ? "\n    Route::put(':id/status', '{$ctrl}/status');"
                : '';
            $groups[] = "// {$ent['model']} 管理\n"
                . "Route::group('{$group}', function () {\n"
                . "    Route::get('', '{$ctrl}/index');\n"
                . "    Route::post('batch-delete', '{$ctrl}/batchDelete');{$status}\n"
                . "    Route::get(':id', '{$ctrl}/show');\n"
                . "    Route::post('', '{$ctrl}/store');\n"
                . "    Route::put(':id', '{$ctrl}/update');\n"
                . "    Route::delete(':id', '{$ctrl}/delete');\n"
                . "})->middleware(['admin_auth', 'admin_permission', 'admin_log']);";
        }
        return "<?php\nuse think\\facade\\Route;\n\n" . implode("\n\n", $groups) . "\n";
    }

    /**
     * dev apply：查/建对应 artifact，经 AiArtifactService 门禁应用。
     * 保留 SP2 的 (specId, stageId) 签名与 {ddl_applied, written} 返回，供 CLI/兼容路径使用。
     */
    public function applyDev(string $specId, string $stageId, ?string $projectRootOverride = null): array
    {
        if (!preg_match('/^spec_[0-9a-f]{16}$/', $specId) || !preg_match('/^compile_[0-9a-f]{16}$/', $stageId)) {
            throw new BusinessException('非法的 stage 标识');
        }
        $art = $this->aiArtifactService->findByStage($specId, $stageId);
        if (!$art) {
            throw new BusinessException('未找到对应 artifact，请先编译');
        }
        $res = $this->aiArtifactService->applyArtifact((int) $art['id'], $projectRootOverride);
        return ['ddl_applied' => true, 'written' => $res['written']];
    }

    protected function writeFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new BusinessException('目录创建失败：' . $dir);
        }
        if (file_put_contents($path, $content) === false) {
            throw new BusinessException('文件写入失败：' . $path);
        }
    }
}
