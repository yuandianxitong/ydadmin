<?php

declare(strict_types=1);

namespace app\repository\agreement;

use app\model\agreement\Agreement;
use core\base\Model;
use core\base\Repository;
use core\support\Like;

/**
 * 协议仓储（agreements 表）。由代码生成器生成，可直接手改。
 *
 * 不受数据权限约束：表里既没有 created_by 也没有 dept_id。不声明 $dataScoped，沿用基类默认值。
 */
class AgreementRepository extends Repository
{
    /** @var list<string> */
    protected array $sortable = ['id', 'created_at', 'updated_at'];

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new Agreement();
    }

    /**
     * 列表查询，搜索项：title（模糊）、code（模糊）、status（精确）。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getAgreementList(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

        $searchTitle = trim((string) ($params['title'] ?? ''));
        if ($searchTitle !== '') {
            $query->where($this->qualify('title'), 'like', Like::contains($searchTitle));
        }

        $searchCode = trim((string) ($params['code'] ?? ''));
        if ($searchCode !== '') {
            $query->where($this->qualify('code'), 'like', Like::contains($searchCode));
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where($this->qualify('status'), (int) $params['status']);
        }

        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'id desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * code 是否已被占用（表上有唯一索引，Service 层写入前调它查重）。
     *
     */
    public function existsByCode(string $value, ?int $excludeId = null): bool
    {
        $query = $this->query()->where($this->qualify('code'), $value);
        if ($excludeId !== null) {
            $query->where($this->qualify('id'), '<>', $excludeId);
        }

        return $query->exists();
    }
}
