<?php

declare(strict_types=1);

namespace app\repository\diy;

use app\model\diy\DiyPageVersion;
use core\base\Model;
use core\base\Repository;

/**
 * 装修页面版本仓储（diy_page_versions 表）。
 *
 * 不受数据权限约束：created_by 是操作人，不是数据归属。
 * 不声明 $dataScoped，沿用基类默认值。
 */
class DiyPageVersionRepository extends Repository
{
    /** @var list<string> */
    protected array $sortable = ['id', 'version_no', 'created_at'];

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new DiyPageVersion();
    }

    /**
     * 插入一版快照。version_no = 该 page_id 当前 max + 1。
     *
     * @param list<array<string, mixed>> $components
     * @param array<string, mixed> $pageSettings
     */
    public function insertSnapshot(int $pageId, array $components, array $pageSettings, int $createdBy, string $note = ''): int
    {
        $versionNo = (int) $this->query()->where($this->qualify('page_id'), $pageId)->max('version_no') + 1;
        $row = $this->create([
            'page_id'       => $pageId,
            'version_no'    => $versionNo,
            'components'    => $components,
            'page_settings' => $pageSettings,
            'note'          => $note,
            'created_by'    => $createdBy,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return (int) $row['id'];
    }

    /**
     * 版本列表：仅 id / version_no / created_at / note，version_no 倒序。
     *
     * @return list<array<string, mixed>>
     */
    public function listByPageId(int $pageId): array
    {
        return $this->applyOrder(
            $this->query()->where($this->qualify('page_id'), $pageId),
            'version_no desc'
        )->select(['id', 'version_no', 'created_at', 'note'])
            ->get()
            ->toArray();
    }
}
