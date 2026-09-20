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
 * 协议无软删：existsByCode / findPublishedByCode 一律从 $this->query() 起手。
 */
class AgreementRepository extends Repository
{
    public const ENABLED = 1;

    /** @var list<string> */
    protected array $sortable = ['id', 'created_at', 'updated_at'];

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new Agreement();
    }

    /**
     * 管理端列表：keyword=title like，另滤 status，id desc。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getAgreementList(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where($this->qualify('title'), 'like', Like::contains($keyword));
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where($this->qualify('status'), (int) $params['status']);
        }

        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'id desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * C 端按编码取已启用协议。status≠ENABLED / 不存在 → null。
     *
     * @return array<string, mixed>|null
     */
    public function findPublishedByCode(string $code): ?array
    {
        $row = $this->query()
            ->where($this->qualify('code'), $code)
            ->where($this->qualify('status'), self::ENABLED)
            ->first();

        return $row?->toArray();
    }

    /**
     * code 是否已被占用（表上有唯一索引，无软删，query() 即全表）。
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
