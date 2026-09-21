<?php

declare(strict_types=1);

namespace app\repository\dataimport;

use app\model\dataimport\DataImport;
use core\base\Model;
use core\base\Repository;

/**
 * 数据导入记录仓储（data_imports 表）。
 *
 * 不受数据权限约束：表里是 admin_id，没有 created_by / dept_id。不声明 $dataScoped。
 * Service 禁止引用 app\model\* 的常量，经这里的状态常量取值。
 */
class DataImportRepository extends Repository
{
    public const STATUS_PROCESSING = 0;

    public const STATUS_COMPLETED = 1;

    public const STATUS_FAILED = 2;

    /** @var list<string> */
    protected array $sortable = ['id', 'created_at', 'updated_at'];

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new DataImport();
    }

    /**
     * 导入历史：可选 module 精确匹配，id desc。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getHistory(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

        $module = trim((string) ($params['module'] ?? ''));
        if ($module !== '') {
            $query->where($this->qualify('module'), $module);
        }

        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'id desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }
}
