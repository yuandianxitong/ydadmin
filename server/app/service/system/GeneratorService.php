<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\SchemaRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\generator\TypeInference;
use DI\Attribute\Inject;

/**
 * 代码生成器编排层（spec §5.3）。本任务只落地只读的前两步：选表、取字段；
 * preview()/generate() 由后续任务在本类补齐，届时会复用这里注入的 SchemaRepository。
 */
class GeneratorService extends Service
{
    #[Inject]
    protected SchemaRepository $schemaRepository;

    #[Inject]
    protected TypeInference $typeInference;

    /**
     * @return list<array{name: string, comment: string, engine: string, rows: int}>
     */
    public function getTables(): array
    {
        return $this->schemaRepository->listTables();
    }

    /**
     * 十二字段的 ColumnDescriptor::toArray() 列表（契约 §4.2）。表不存在时抛业务异常。
     *
     * @return list<array<string, mixed>>
     */
    public function getColumns(string $table): array
    {
        if (!$this->schemaRepository->tableExists($table)) {
            throw new BusinessException(lang('generator.table_not_found'));
        }

        return array_map(
            fn (array $raw): array => $this->typeInference->describe($raw)->toArray(),
            $this->schemaRepository->listColumns($table)
        );
    }
}
