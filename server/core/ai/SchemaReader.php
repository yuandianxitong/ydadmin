<?php
declare(strict_types=1);

namespace core\ai;

use app\service\system\CodeGeneratorService;

/**
 * 读取数据库表结构并转换为引擎 GenerateRequest.schema_input 契约
 * 数据访问全部委托 CodeGeneratorService（Db::query 仅允许存在于该类的项目规矩）
 */
class SchemaReader
{
    public function buildSchemaInput(array $tables): array
    {
        $service = new CodeGeneratorService();
        $result = [];
        foreach ($tables as $table) {
            try {
                $columns = $service->getTableColumns($table);
            } catch (\Throwable $e) {
                throw new AiClientException("数据表 '{$table}' 不存在或无法访问");
            }
            if (empty($columns)) {
                throw new AiClientException("数据表 '{$table}' 没有字段信息");
            }
            $result[] = [
                'name'    => $table,
                'columns' => array_map(static fn (array $c) => [
                    'name'     => $c['name'],
                    'type'     => $c['raw_type'],
                    'key'      => $c['key'] ?? '',
                    'default'  => $c['default'],
                    'comment'  => $c['comment'] ?? '',
                    'nullable' => (bool) $c['nullable'],
                ], $columns),
                'indexes' => [],
            ];
        }
        return ['tables' => $result];
    }
}
