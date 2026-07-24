<?php
declare(strict_types=1);

namespace app\repository\ai;

use app\model\ai\AiArtifact;
use core\base\Repository;
use think\Model;

class AiArtifactRepository extends Repository
{
    protected function getModel(): Model
    {
        return new AiArtifact();
    }

    /** @return array<int,array> 某 spec 的全部 artifact，新到旧 */
    public function listBySpec(string $specId): array
    {
        return $this->model->where('spec_id', $specId)->order('id desc')->select()->toArray();
    }

    /**
     * 条件跃迁：仅当当前 state 命中 $from 才更新为 $to，返回受影响行数（用于并发防重）。
     * @param array<int,string> $from
     */
    public function transition(int $id, array $from, string $to, array $extra = []): int
    {
        return $this->model->where('id', $id)
            ->whereIn('state', $from)
            ->update(array_merge(['state' => $to], $extra));
    }

    /** 把同 spec 下其它非终态 artifact 置 superseded，返回受影响行数 */
    public function supersedeOthers(string $specId, int $keepId): int
    {
        return $this->model->where('spec_id', $specId)
            ->where('id', '<>', $keepId)
            ->whereIn('state', ['compiled', 'checking', 'checked_passed', 'checked_failed'])
            ->update(['state' => 'superseded']);
    }
}
