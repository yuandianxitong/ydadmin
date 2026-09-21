<?php

declare(strict_types=1);

namespace app\repository\version;

use app\model\version\AppVersion;
use core\base\Model;
use core\base\Repository;

/**
 * 应用版本仓储（app_versions 表）。由代码生成器生成，可直接手改。
 *
 * 不受数据权限约束：表里既没有 created_by 也没有 dept_id。不声明 $dataScoped，沿用基类默认值。
 */
class AppVersionRepository extends Repository
{
    public const ENABLED = 1;

    /** @var list<string> */
    protected array $sortable = ['id', 'version_code', 'created_at', 'updated_at'];

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new AppVersion();
    }

    /**
     * 列表查询，搜索项：platform、status（精确），version_code desc。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getAppVersionList(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

        if (isset($params['platform']) && $params['platform'] !== '') {
            $query->where($this->qualify('platform'), (string) $params['platform']);
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where($this->qualify('status'), (int) $params['status']);
        }

        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'version_code desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 指定平台最新启用版本（version_code desc limit 1）。
     *
     * @return array<string, mixed>|null
     */
    public function getLatestEnabled(string $platform): ?array
    {
        $row = $this->applyOrder(
            $this->query()
                ->where($this->qualify('platform'), $platform)
                ->where($this->qualify('status'), self::ENABLED),
            'version_code desc'
        )->limit(1)->first();

        return $row?->toArray();
    }
}
