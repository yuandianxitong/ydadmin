<?php

declare(strict_types=1);

namespace app\repository\demo;

use app\model\demo\GenArticle;
use core\base\Model;
use core\base\Repository;
use core\datascope\DataScope;
use core\support\Like;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * 生成器夹具表仓储（gen_articles 表）。由代码生成器生成，可直接手改。
 *
 * 受数据权限约束：表里有 created_by 与 dept_id，按 spec §5.2 自动接入；不想受控就把 $dataScoped 改成 false。
 */
class GenArticleRepository extends Repository
{
    /** @var list<string> */
    protected array $sortable = ['id', 'sort', 'created_at', 'updated_at'];

    protected bool $dataScoped = true;

    protected ?string $creatorColumn = 'created_by';

    protected ?string $deptColumn = 'dept_id';

    protected function getModel(): Model
    {
        return new GenArticle();
    }

    /**
     * 列表查询，搜索项：title（模糊）、status（精确）。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getGenArticleList(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

        $searchTitle = trim((string) ($params['title'] ?? ''));
        if ($searchTitle !== '') {
            $query->where($this->qualify('title'), 'like', Like::contains($searchTitle));
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where($this->qualify('status'), (int) $params['status']);
        }

        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'sort asc, id desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * slug 是否已被占用（表上有唯一索引，Service 层写入前调它查重）。
     *
     * 经 DataScope::bypass() 看全表：查重必须看见数据范围外的行，否则受限管理员插得进重复值，再被唯一索引打成 500。
     * 含软删行：唯一索引对软删行同样生效。
     */
    public function existsBySlug(string $value, ?int $excludeId = null): bool
    {
        return DataScope::bypass(function () use ($value, $excludeId): bool {
            $query = $this->query()->withoutGlobalScope(SoftDeletingScope::class)->where($this->qualify('slug'), $value);
            if ($excludeId !== null) {
                $query->where($this->qualify('id'), '<>', $excludeId);
            }

            return $query->exists();
        });
    }
}
