<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\DictionaryItem;
use core\base\Model;
use core\base\Repository;

/** 字典项仓储（不受数据权限约束）。 */
class DictionaryItemRepository extends Repository
{
    /** @var list<string> */
    protected array $sortable = ['id', 'sort'];

    protected function getModel(): Model
    {
        return new DictionaryItem();
    }

    /**
     * 某字典的全部未删除项（任意状态），sort、id 升序。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getByDictionaryId(int $dictionaryId): array
    {
        return $this->query()->where($this->qualify('dictionary_id'), $dictionaryId)
            ->orderBy($this->qualify('sort'))->orderBy($this->qualify('id'))
            ->get()->toArray();
    }

    /** 同一字典内的值是否被未删除的项占用。与软删行的冲突由唯一索引兜底。 */
    public function existsValue(int $dictionaryId, string $value, int $excludeId = 0): bool
    {
        $query = $this->query()->where($this->qualify('dictionary_id'), $dictionaryId)->where($this->qualify('value'), $value);
        if ($excludeId > 0) {
            $query->where($this->qualify('id'), '<>', $excludeId);
        }

        return $query->exists();
    }
}
