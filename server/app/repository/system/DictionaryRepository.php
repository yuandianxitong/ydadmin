<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\Dictionary;
use core\base\Model;
use core\base\Repository;
use support\Cache;

/**
 * 数据字典仓储（不受数据权限约束）。
 *
 * options 缓存：键 dict.{code}，值为该字典的启用项列表；字典不存在或已禁用时缓存 []。TTL 7200 秒。
 * 字典或字典项的每一次写入都必须经 forgetOptions() 清掉受影响的编码（DictionaryService 在 afterCommit 里调用）。
 */
class DictionaryRepository extends Repository
{
    /** 字典编码字符集：与校验规则 alpha_dash:ascii 一致，也保证缓存键不含 PSR-16 保留字符。 */
    public const CODE_PATTERN = '/^[A-Za-z0-9_-]{1,100}$/';

    private const CACHE_PREFIX = 'dict.';

    private const CACHE_TTL = 7200;

    /** @var list<string> */
    protected array $sortable = ['id', 'sort', 'created_at'];

    protected function getModel(): Model
    {
        return new Dictionary();
    }

    /**
     * 列表：每行含 items_count（未删除的字典项数，不分状态）。支持 keyword（name/code 模糊）、status。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getListWithItemCount(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query()->withCount('items');

        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(function ($q) use ($like): void {
                $q->where($this->qualify('name'), 'like', $like)->orWhere($this->qualify('code'), 'like', $like);
            });
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where($this->qualify('status'), (int) $params['status']);
        }

        $total = (clone $query)->count();
        $list = $this->applyOrder($query, 'sort asc, created_at desc, id desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 详情：字典行 + 全部未删除的字典项（不分启用/禁用，契约 §2.6）。
     *
     * @return array<string, mixed>|null
     */
    public function findWithItems(int $id): ?array
    {
        return $this->query()->with('items')->where($this->qualify('id'), $id)->first()?->toArray();
    }

    /** 编码是否被未删除的字典占用。与软删行的冲突由唯一索引兜底（见 DictionaryService 类注释）。 */
    public function existsCode(string $code, int $excludeId = 0): bool
    {
        $query = $this->query()->where($this->qualify('code'), $code);
        if ($excludeId > 0) {
            $query->where($this->qualify('id'), '<>', $excludeId);
        }

        return $query->exists();
    }

    /**
     * 下拉选项：字典启用时返回其启用项（sort、id 升序），否则 []。结果缓存 dict.{code}。
     * 编码不符合 CODE_PATTERN 时直接返回 []：不查库，也不写缓存（非法字符进缓存键会抛异常）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOptionsByCode(string $code): array
    {
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            return [];
        }
        $cached = Cache::get(self::CACHE_PREFIX . $code);
        if (is_array($cached)) {
            return $cached;
        }

        $dictionary = $this->query()
            ->with(['items' => static function ($relation): void {
                $relation->where('dictionary_items.status', 1);
            }])
            ->where($this->qualify('code'), $code)
            ->where($this->qualify('status'), 1)
            ->first();
        $options = $dictionary === null ? [] : (array) ($dictionary->toArray()['items'] ?? []);
        Cache::set(self::CACHE_PREFIX . $code, $options, self::CACHE_TTL);

        return $options;
    }

    /**
     * 清掉若干编码的 options 缓存。
     *
     * @param array<int, string> $codes
     */
    public function forgetOptions(array $codes): void
    {
        foreach (array_unique($codes) as $code) {
            if (preg_match(self::CODE_PATTERN, $code) === 1) {
                Cache::delete(self::CACHE_PREFIX . $code);
            }
        }
    }
}
