<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\diy;

use app\model\diy\DiyLink;
use core\base\Repository;
use think\Model;

class DiyLinkRepository extends Repository
{
    protected function getModel(): Model
    {
        return new DiyLink();
    }

    public function listAll(): array
    {
        return $this->model->order('sort', 'asc')->order('id', 'desc')->select()->toArray();
    }

    /** @return array<int,array{label:string,path:string,category:string}> */
    public function listLibraryLinks(): array
    {
        $rows = $this->model->where('status', 1)->order('sort', 'asc')->select()->toArray();
        return array_map(static fn (array $r): array => [
            'label'    => (string) $r['label'],
            'path'     => (string) $r['path'],
            'category' => (string) ($r['category'] ?: '我的链接'),
        ], $rows);
    }
}
