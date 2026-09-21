<?php

declare(strict_types=1);

namespace app\repository\diy;

use app\model\diy\DiyPage;
use core\base\Model;
use core\base\Repository;
use core\support\Like;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * 装修页面仓储（diy_pages 表）。
 *
 * 不受数据权限约束：装修页是全站一份，表里没有 created_by / dept_id。
 * 不声明 $dataScoped，沿用基类默认值。
 */
class DiyPageRepository extends Repository
{
    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new DiyPage();
    }

    /**
     * 按 page_key + platform 取未删行。
     *
     * @return array<string, mixed>|null
     */
    public function findByKey(string $key, string $platform = 'uniapp'): ?array
    {
        return $this->query()
            ->where($this->qualify('page_key'), $key)
            ->where($this->qualify('platform'), $platform)
            ->first()?->toArray();
    }

    /**
     * page_key + platform 是否已被占用（含软删行：uk_pagekey_platform 不区分软删）。
     */
    public function existsKey(string $key, string $platform = 'uniapp'): bool
    {
        return $this->query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where($this->qualify('page_key'), $key)
            ->where($this->qualify('platform'), $platform)
            ->exists();
    }

    /**
     * 把已规范化的数组写入已发布树与页面设置，不要 Db::raw 拷列。
     *
     * @param list<array<string, mixed>> $components
     * @param array<string, mixed> $pageSettings
     */
    public function publishByKey(string $key, array $components, array $pageSettings, string $platform = 'uniapp'): void
    {
        $this->query()
            ->where($this->qualify('page_key'), $key)
            ->where($this->qualify('platform'), $platform)
            ->update([
                'components_published' => $components,
                'page_settings'        => $pageSettings,
            ]);
    }

    /**
     * 自定义页分页列表（仅 uniapp）。不含 home/member。
     *
     * @return array{list: list<array<string, mixed>>, total: int}
     */
    public function listPages(int $page = 1, int $limit = 10, string $keyword = '', ?bool $published = null): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));

        $query = $this->query()
            ->where($this->qualify('page_type'), 'custom')
            ->where($this->qualify('platform'), 'uniapp');

        $keyword = trim($keyword);
        if ($keyword !== '') {
            $query->where($this->qualify('title'), 'like', Like::contains($keyword));
        }

        $publishedCol = $this->qualify('components_published');
        if ($published === true) {
            $query->whereRaw("JSON_LENGTH({$publishedCol}) > 0");
        } elseif ($published === false) {
            $query->whereRaw("({$publishedCol} IS NULL OR JSON_LENGTH({$publishedCol}) = 0)");
        }

        $total = (clone $query)->count();
        $rows = $this->applyOrder($query, 'updated_at desc')
            ->forPage($page, $limit)
            ->select([
                $this->qualify('id'),
                $this->qualify('page_key'),
                $this->qualify('title'),
                $this->qualify('status'),
                $this->qualify('updated_at'),
                $this->qualify('components_draft'),
                $this->qualify('components_published'),
            ])
            ->get()
            ->toArray();

        $list = [];
        foreach ($rows as $row) {
            $draftCount = $this->countComponents($row['components_draft'] ?? null);
            $publishedCount = $this->countComponents($row['components_published'] ?? null);
            unset($row['components_draft'], $row['components_published']);
            $row['component_count'] = $draftCount > 0 ? $draftCount : $publishedCount;
            $row['published'] = $publishedCount > 0;
            $list[] = $row;
        }

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 已启用自定义页转链接项（供 Task 5 链接目录用）。
     *
     * @return list<array{label: string, path: string}>
     */
    public function listCustomLinkPages(): array
    {
        $rows = $this->applyOrder(
            $this->query()
                ->where($this->qualify('page_type'), 'custom')
                ->where($this->qualify('platform'), 'uniapp')
                ->where($this->qualify('status'), 1),
            'updated_at desc'
        )->select([$this->qualify('page_key'), $this->qualify('title')])
            ->get()
            ->toArray();

        return array_map(static fn (array $r): array => [
            'label' => (string) ($r['title'] ?? $r['page_key']),
            'path'  => '/pages/diy/index?key=' . $r['page_key'],
        ], $rows);
    }

    /** 软删，不改 page_key（uk_pagekey_platform 含软删行，同 key 再建走 422）。 */
    public function softDelete(int $id): void
    {
        $this->query()->whereKey($id)->delete();
    }

    /** @param mixed $raw */
    private function countComponents(mixed $raw): int
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return 0;
        }
        $n = 0;
        foreach ($raw as $c) {
            if (is_string($c)) {
                $decoded = json_decode($c, true);
                $c = is_array($decoded) ? $decoded : null;
            }
            if (is_array($c) && ($c['id'] ?? '') !== '' && ($c['type'] ?? '') !== '') {
                $n++;
            }
        }

        return $n;
    }
}
