<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\File;
use core\base\Model;
use core\base\Repository;
use core\support\Like;
use Illuminate\Database\Eloquent\Builder;

/**
 * 文件仓储（契约 §2.9.1）。
 *
 * 不受数据权限约束：spec §5.5 的接入清单只有 admins（M1a）与两张日志表（M1b），素材库对所有
 * 持有 system.file.* 的管理员是同一份。所以 $dataScoped 保持 false——这是有意的，不是漏配。
 * 表里也没有 created_by（归属人列是 upload_by，只展示不过滤），故 $creatorColumn = null。
 *
 * files 没有 sort 列，$sortable 相应重写，避免基类默认值让 'sort asc' 撞上不存在的列。
 */
class FileRepository extends Repository
{
    /** document 分桶的 MIME 白名单（照 TP8 FileService::getFileList）。 */
    public const DOCUMENT_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
    ];

    /** archive 分桶的 MIME 白名单（照 TP8）。 */
    public const ARCHIVE_MIMES = [
        'application/zip',
        'application/x-rar-compressed',
        'application/vnd.rar',
        'application/x-7z-compressed',
        'application/x-tar',
        'application/gzip',
        'application/x-bzip2',
    ];

    /** other 分桶：不属于这三类前缀的都算。 */
    private const MEDIA_PREFIXES = ['image', 'video', 'audio'];

    /** @var list<string> */
    protected array $sortable = ['id', 'name', 'size', 'created_at', 'updated_at'];

    /** 不受数据权限约束：spec §5.5 只接入 admins 与两张日志表，素材库是共享资源——显式写出来，免得后来的人以为漏配。 */
    protected bool $dataScoped = false;

    /** files 表没有 created_by 列：$dataScoped 为 false 时基类本来就不会填，写出来是防止将来改动把插入弄挂。 */
    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new File();
    }

    /**
     * 文件列表。keyword 模糊匹配文件名，group 精确匹配，mime_type 是前端的六个分桶之一。
     *
     * @param array<string, mixed> $params keyword、group、mime_type（image/video/audio/document/archive/other）
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getFileList(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

        $keyword = trim((string) ($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where($this->qualify('name'), 'like', Like::contains($keyword));
        }

        $group = trim((string) ($params['group'] ?? ''));
        if ($group !== '') {
            $query->where($this->qualify('group'), $group);
        }

        $bucket = trim((string) ($params['mime_type'] ?? ''));
        if ($bucket !== '') {
            $this->applyMimeBucket($query, $bucket);
        }

        $total = (clone $query)->count();
        $list = $query->orderBy($this->qualify('id'), 'desc')->forPage($page, $limit)->get()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 分组聚合：[{group, count}]，按 count 降序、组名升序（第二排序键让结果稳定）。
     * 软删行由 SoftDeletes 全局作用域自动排除。
     *
     * @return list<array{group: string, count: int}>
     */
    public function getGroupCounts(): array
    {
        $rows = $this->query()
            ->select($this->qualify('group'))
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy($this->qualify('group'))
            ->orderByDesc('aggregate_count')
            ->orderBy($this->qualify('group'))
            ->get();

        $groups = [];
        foreach ($rows as $row) {
            $groups[] = ['group' => (string) $row->group, 'count' => (int) $row->aggregate_count];
        }

        return $groups;
    }

    /**
     * 分桶翻译成查询条件（照 TP8 FileService::getFileList 的值域）。
     *
     * 分桶名来自前端固定的六个选项，不是用户自由输入的搜索词，所以前缀串不经 Like::contains()：
     * 那个工具会把 % 转义掉，正好破坏这里要的前缀匹配。白名单外的值一律忽略，不做额外过滤。
     *
     * @param Builder<Model> $query
     */
    private function applyMimeBucket(Builder $query, string $bucket): void
    {
        $column = $this->qualify('mime_type');
        if (in_array($bucket, self::MEDIA_PREFIXES, true)) {
            $query->where($column, 'like', $bucket . '/%');

            return;
        }
        if ($bucket === 'document') {
            $query->whereIn($column, self::DOCUMENT_MIMES);

            return;
        }
        if ($bucket === 'archive') {
            $query->whereIn($column, self::ARCHIVE_MIMES);

            return;
        }
        if ($bucket === 'other') {
            foreach (self::MEDIA_PREFIXES as $prefix) {
                $query->where($column, 'not like', $prefix . '/%');
            }
        }
    }
}
