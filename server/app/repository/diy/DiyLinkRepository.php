<?php

declare(strict_types=1);

namespace app\repository\diy;

use app\model\diy\DiyLink;
use core\base\Model;
use core\base\Repository;

/**
 * 装修链接库仓储（diy_links 表）。
 *
 * 不受数据权限约束：链接库是全站一份，表里没有 created_by / dept_id。
 * 不声明 $dataScoped，沿用基类默认值。
 */
class DiyLinkRepository extends Repository
{
    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new DiyLink();
    }

    /**
     * 管理端全部链接（含禁用），裸数组。
     *
     * @return list<array<string, mixed>>
     */
    public function listAll(): array
    {
        return $this->applyOrder($this->query(), 'sort asc, id desc')->get()->toArray();
    }

    /**
     * 已启用链接转目录项（供 LinkCatalog 合并）。
     *
     * @return list<array{label: string, path: string, category: string}>
     */
    public function listLibraryLinks(): array
    {
        $rows = $this->applyOrder(
            $this->query()->where($this->qualify('status'), 1),
            'sort asc'
        )->get()->toArray();

        return array_map(static fn (array $r): array => [
            'label'    => (string) $r['label'],
            'path'     => (string) $r['path'],
            'category' => (string) ($r['category'] ?: '我的链接'),
        ], $rows);
    }
}
