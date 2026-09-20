<?php

declare(strict_types=1);

namespace app\repository\feedback;

use app\model\feedback\Feedback;
use core\base\Model;
use core\base\Repository;
use core\support\Like;
use Illuminate\Database\Eloquent\Builder;

/**
 * 反馈仓储（feedbacks 表）。
 *
 * 不受数据权限约束：表里既没有 created_by 也没有 dept_id。不声明 $dataScoped，沿用基类默认值。
 * C 端归属隔离靠 getUserList / findForUser 显式带 user_id，不靠数据范围。
 * Service 禁止引用 app\model\* 的常量（check:context 规则三），经这里转发取值。
 */
class FeedbackRepository extends Repository
{
    public const STATUS_PENDING = Feedback::STATUS_PENDING;

    public const STATUS_PROCESSING = Feedback::STATUS_PROCESSING;

    public const STATUS_REPLIED = Feedback::STATUS_REPLIED;

    public const STATUS_CLOSED = Feedback::STATUS_CLOSED;

    /** @var list<string> */
    protected array $sortable = ['id', 'created_at', 'updated_at'];

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new Feedback();
    }

    /**
     * 管理端列表：keyword=content like，另滤 type / status，id desc。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getSearchList(array $params, int $page, int $limit): array
    {
        $query = $this->query();

        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where($this->qualify('content'), 'like', Like::contains($keyword));
        }

        if (isset($params['type']) && $params['type'] !== '') {
            $query->where($this->qualify('type'), (string) $params['type']);
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where($this->qualify('status'), (int) $params['status']);
        }

        return $this->paginateIdDesc($query, $page, $limit);
    }

    /**
     * C 端本人列表，id desc。
     *
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getUserList(int $userId, int $page, int $limit): array
    {
        $query = $this->query()->where($this->qualify('user_id'), $userId);

        return $this->paginateIdDesc($query, $page, $limit);
    }

    /**
     * C 端详情：只查本人。他人行与不存在一律返回 null，调用方统一 404。
     *
     * @return array<string, mixed>|null
     */
    public function findForUser(int $userId, int $id): ?array
    {
        return $this->query()
            ->where($this->qualify('id'), $id)
            ->where($this->qualify('user_id'), $userId)
            ->first()?->toArray();
    }

    /**
     * @param Builder<Model> $query
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    private function paginateIdDesc(Builder $query, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'id desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }
}
