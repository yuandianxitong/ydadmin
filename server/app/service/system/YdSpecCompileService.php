<?php
declare(strict_types=1);

namespace app\service\system;

use core\ai\FileWriter;
use core\ai\ydspec\YdSpecCompiler;
use core\ai\ydspec\YdSpecValidator;
use core\base\Service;
use core\database\SqlRunner;
use core\exception\BusinessException;
use think\facade\Db;

class YdSpecCompileService extends Service
{
    protected YdSpecValidator $ydSpecValidator;

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

        $manifest = [
            'spec_id'      => $specId,
            'stage_id'     => $stageId,
            'created_at'   => date('Y-m-d H:i:s'),
            'schema_patch' => 'schema_patch.sql',
            'update_sql'   => 'update.sql',
            'files'        => $manifestFiles,
        ];
        $this->writeFile(
            $dir . '/manifest.json',
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return [
            'stage_id'     => $stageId,
            'dir'          => 'runtime/ai/specs/' . $specId . '/' . $stageId,
            'schema_patch' => $compiled['schema_patch'],
            'update_sql'   => $compiled['update_sql'],
            'files'        => $manifestFiles,
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
     * 最小 dev apply：把 DDL 刷入当前库 + 把代码文件写入项目（自验用）。
     * 正式 apply 门禁/状态机留子项目 3。
     */
    public function applyDev(string $specId, string $stageId, ?string $projectRootOverride = null): array
    {
        if (!preg_match('/^spec_[0-9a-f]{16}$/', $specId) || !preg_match('/^compile_[0-9a-f]{16}$/', $stageId)) {
            throw new BusinessException('非法的 stage 标识');
        }
        $dir = $this->specsBase() . '/' . $specId . '/' . $stageId;
        $manifestFile = $dir . '/manifest.json';
        if (!is_file($manifestFile)) {
            throw new BusinessException('stage 不存在或已过期');
        }
        $manifest = json_decode((string) file_get_contents($manifestFile), true);
        if (!is_array($manifest)) {
            throw new BusinessException('manifest 解析失败');
        }

        // 1) 执行 DDL 到当前（dev）库
        $this->runDdl((string) file_get_contents($dir . '/update.sql'));

        // 2) 写代码文件到项目：后端相对 server/，admin/ 相对项目根
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
            if (copy($src, $target)) {
                $written[] = $rel;
            }
        }

        return ['ddl_applied' => true, 'written' => $written];
    }

    protected function runDdl(string $sql): void
    {
        $pdo = Db::connect()->getPdo();
        $prefix = (string) Db::connect()->getConfig('prefix');
        (new SqlRunner($pdo, $prefix))->runSql($sql);
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
