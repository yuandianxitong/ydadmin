<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\diy;

use app\model\diy\DiyPage;
use app\model\diy\DiyPageVersion;
use core\base\Repository;
use think\facade\Db;
use think\Model;

class DiyPageRepository extends Repository
{
    protected function getModel(): Model
    {
        return new DiyPage();
    }

    protected function query()
    {
        return $this->model->whereNull('deleted_at');
    }

    public function findByKey(string $key, string $platform = 'uniapp'): ?array
    {
        $row = $this->query()
            ->where('page_key', $key)
            ->where('platform', $platform)
            ->find();
        return $row ? $row->toArray() : null;
    }

    public function findHome(): ?array
    {
        return $this->findByKey('home');
    }

    public function publishByKey(string $key): int
    {
        return $this->query()
            ->where('page_key', $key)
            ->where('platform', 'uniapp')
            ->update([
                'components_published' => Db::raw('`components_draft`'),
                'updated_at'           => date('Y-m-d H:i:s'),
            ]);
    }

    public function publishHome(): int
    {
        return $this->publishByKey('home');
    }

    /** @return array<int,array{label:string,path:string}> */
    public function listCustomLinkPages(): array
    {
        $rows = $this->query()
            ->where('page_type', 'custom')
            ->where('platform', 'uniapp')
            ->where('status', 1)
            ->order('updated_at', 'desc')
            ->field(['page_key', 'title'])
            ->select()
            ->toArray();
        return array_map(static fn (array $r): array => [
            'label' => (string) ($r['title'] ?? $r['page_key']),
            'path'  => '/pages/diy/index?key=' . $r['page_key'],
        ], $rows);
    }

    /**
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function listPages(int $page = 1, int $limit = 10, string $keyword = '', ?bool $published = null): array
    {
        $base = function () use ($keyword, $published) {
            $q = $this->query()
                ->where('page_type', 'custom')
                ->where('platform', 'uniapp');
            if ($keyword !== '') {
                $q->whereLike('title', '%' . $keyword . '%');
            }
            if ($published === true) {
                $q->whereRaw('components_published IS NOT NULL AND JSON_LENGTH(components_published) > 0');
            } elseif ($published === false) {
                $q->whereRaw('(components_published IS NULL OR JSON_LENGTH(components_published) = 0)');
            }
            return $q;
        };

        $total = $base()->count();
        $rows = $base()
            ->order('updated_at', 'desc')
            ->page($page, $limit)
            ->field(['id', 'page_key', 'title', 'status', 'updated_at', 'components_draft', 'components_published'])
            ->select()
            ->toArray();

        $list = array_map(static function (array $r): array {
            $draft = self::countComponents($r['components_draft'] ?? null);
            $published = self::countComponents($r['components_published'] ?? null);
            unset($r['components_draft'], $r['components_published']);
            $r['component_count'] = $draft > 0 ? $draft : $published;
            $r['published'] = $published > 0;
            return $r;
        }, $rows);

        return ['list' => $list, 'total' => $total];
    }

    /** @param mixed $raw */
    private static function countComponents(mixed $raw): int
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

    public function existsKey(string $key): bool
    {
        return $this->query()
            ->where('page_key', $key)
            ->where('platform', 'uniapp')
            ->count() > 0;
    }

    public function renamePage(int $id, string $title): void
    {
        $this->update($id, ['title' => $title, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function updateSlug(int $id, string $key): void
    {
        $this->update($id, ['page_key' => $key, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function setStatus(int $id, int $status): void
    {
        $this->update($id, ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function softDelete(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->update($id, [
            'page_key'   => Db::raw("LEFT(CONCAT('del_', `id`, '_', `page_key`), 64)"),
            'deleted_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function insertVersion(int $pageId, array $components, array $pageSettings, int $createdBy): int
    {
        $no = (int) (new DiyPageVersion())->where('page_id', $pageId)->max('version_no') + 1;
        (new DiyPageVersion())->save([
            'page_id'       => $pageId,
            'version_no'    => $no,
            'components'    => $components,
            'page_settings' => $pageSettings,
            'note'          => '',
            'created_by'    => $createdBy,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        return $no;
    }

    public function listVersions(int $pageId): array
    {
        return (new DiyPageVersion())
            ->where('page_id', $pageId)
            ->order('version_no', 'desc')
            ->select()
            ->toArray();
    }

    public function findVersion(int $versionId): ?array
    {
        $row = (new DiyPageVersion())->find($versionId);
        return $row ? $row->toArray() : null;
    }

    public function restoreDraft(int $pageId, array $components, array $pageSettings): void
    {
        // Model JSON cast：传数组，避免二次编码
        $this->update($pageId, [
            'components_draft' => $components,
            'page_settings'    => $pageSettings,
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
    }
}
