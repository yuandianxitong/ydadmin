<?php
declare(strict_types=1);

namespace app\repository\system;

use app\model\system\DictionaryItem;
use core\base\Repository;
use think\Model;

class DictionaryItemRepository extends Repository
{
    protected function getModel(): Model
    {
        return new DictionaryItem();
    }

    /**
     * 获取指定字典的所有项
     */
    public function getByDictionaryId(int $dictionaryId): array
    {
        return $this->model
            ->where('dictionary_id', $dictionaryId)
            ->order('sort asc, id asc')
            ->select()
            ->toArray();
    }

    /**
     * 检查同一字典下 value 是否重复
     */
    public function existsValue(int $dictionaryId, string $value, int $excludeId = 0): bool
    {
        $query = $this->model
            ->where('dictionary_id', $dictionaryId)
            ->where('value', $value);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }
}
