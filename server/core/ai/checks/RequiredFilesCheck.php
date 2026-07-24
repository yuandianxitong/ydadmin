<?php
declare(strict_types=1);

namespace core\ai\checks;

/**
 * 断言每实体齐全（Model/Repository/Service/Controller/Validate + api.ts）+ 模块路由文件。
 */
class RequiredFilesCheck implements CheckInterface
{
    public function name(): string
    {
        return 'required_files';
    }

    public function check(CheckContext $ctx): array
    {
        $present = [];
        foreach ($ctx->manifest['files'] ?? [] as $f) {
            $present[(string) ($f['path'] ?? '')] = true;
        }

        $expected = [];
        $module = '';
        foreach ($ctx->entities as $e) {
            $m = (string) ($e['module'] ?? '');
            $model = (string) ($e['model'] ?? '');
            $group = (string) ($e['route_group'] ?? '');
            $module = $m;
            $expected[] = "app/model/{$m}/{$model}.php";
            $expected[] = "app/repository/{$m}/{$model}Repository.php";
            $expected[] = "app/service/{$m}/{$model}Service.php";
            $expected[] = "app/adminapi/controller/v1/{$m}/{$model}Controller.php";
            $expected[] = "app/adminapi/validate/v1/{$m}/{$model}Validate.php";
            $expected[] = "admin/src/api/{$group}.ts";
        }
        if ($module !== '') {
            $expected[] = "app/adminapi/route/{$module}.php";
        }

        $results = [];
        foreach (array_unique($expected) as $path) {
            if (!isset($present[$path])) {
                $results[] = new CheckResult($this->name(), 'error', "缺少必需文件：{$path}", $path);
            }
        }
        return $results;
    }
}
