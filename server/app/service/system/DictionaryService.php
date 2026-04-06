<?php
declare(strict_types=1);

namespace app\service\system;

use app\repository\system\DictionaryRepository;
use app\repository\system\DictionaryItemRepository;
use core\base\Service;
use core\exception\BusinessException;
use think\facade\Db;

class DictionaryService extends Service
{
    protected DictionaryRepository $dictRepo;
    protected DictionaryItemRepository $itemRepo;

    // ========== 字典 ==========

    /**
     * 获取字典列表
     */
    public function getDictionaryList(array $params): array
    {
        $where = [];

        if (!empty($params['keyword'])) {
            $where[] = ['name|code', 'like', '%' . $params['keyword'] . '%'];
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int)$params['status']];
        }

        $page  = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 15);

        return $this->dictRepo->getListWithItemCount($where, $page, $limit);
    }

    /**
     * 获取字典详情
     */
    public function getDictionaryDetail(int $id): array
    {
        $result = $this->dictRepo->getDetailWithItems($id);
        if (!$result) {
            throw new BusinessException(lang('business.dict_not_found'));
        }
        return $result;
    }

    /**
     * 创建字典
     */
    public function createDictionary(array $data): array
    {
        if ($this->dictRepo->existsCode($data['code'])) {
            throw new BusinessException(lang('business.dict_code_exists'));
        }

        $result = $this->dictRepo->create([
            'name'        => $data['name'],
            'code'        => $data['code'],
            'description' => $data['description'] ?? '',
            'status'      => $data['status'] ?? 1,
            'sort'        => $data['sort'] ?? 0,
        ]);
        $this->dictRepo->clearCache();
        return $result;
    }

    /**
     * 更新字典
     */
    public function updateDictionary(int $id, array $data): bool
    {
        $dict = $this->dictRepo->find($id);
        if (!$dict) {
            throw new BusinessException(lang('business.dict_not_found'));
        }

        if (isset($data['code']) && $this->dictRepo->existsCode($data['code'], $id)) {
            throw new BusinessException(lang('business.dict_code_exists'));
        }

        $updateData = array_filter([
            'name'        => $data['name'] ?? null,
            'code'        => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'status'      => isset($data['status']) ? (int)$data['status'] : null,
            'sort'        => isset($data['sort']) ? (int)$data['sort'] : null,
        ], fn($v) => $v !== null);

        $result = $this->dictRepo->update($id, $updateData);
        $this->dictRepo->clearCache();
        return $result;
    }

    /**
     * 删除字典
     */
    public function deleteDictionary(int $id): bool
    {
        $this->findOrFail($this->dictRepo, $id, 'business.dict_not_found');

        return $this->runInTransaction(function () use ($id) {
            // 删除字典项
            $this->itemRepo->deleteWhere(['dictionary_id' => $id]);
            // 删除字典
            $result = $this->dictRepo->delete($id);
            $this->dictRepo->clearCache();
            return $result;
        });
    }

    /**
     * 批量删除字典
     *
     * 使用事务包裹：任一删除失败则整体回滚。
     */
    public function batchDeleteDictionary(array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }
        return $this->runInTransaction(function () use ($ids) {
            foreach ($ids as $id) {
                $this->deleteDictionary((int) $id);
            }
            return true;
        });
    }

    /**
     * 根据 code 获取字典选项
     */
    public function getOptionsByCode(string $code): array
    {
        $dict = $this->dictRepo->getByCode($code);
        if (!$dict) {
            return [];
        }
        return $dict['items'] ?? [];
    }

    /**
     * 批量获取字典选项
     */
    public function getOptionsByCodes(array $codes): array
    {
        $result = [];
        foreach ($codes as $code) {
            $result[$code] = $this->getOptionsByCode($code);
        }
        return $result;
    }

    // ========== 字典项 ==========

    /**
     * 获取字典项列表
     */
    public function getItemList(int $dictionaryId): array
    {
        return $this->itemRepo->getByDictionaryId($dictionaryId);
    }

    /**
     * 创建字典项
     */
    public function createItem(array $data): array
    {
        // 验证字典是否存在
        $dict = $this->dictRepo->find($data['dictionary_id']);
        if (!$dict) {
            throw new BusinessException(lang('business.dict_not_found'));
        }

        if ($this->itemRepo->existsValue($data['dictionary_id'], $data['value'])) {
            throw new BusinessException(lang('business.dict_item_value_exists'));
        }

        $result = $this->itemRepo->create([
            'dictionary_id' => $data['dictionary_id'],
            'label'         => $data['label'],
            'value'         => $data['value'],
            'tag_type'      => $data['tag_type'] ?? '',
            'description'   => $data['description'] ?? '',
            'status'        => $data['status'] ?? 1,
            'sort'          => $data['sort'] ?? 0,
        ]);
        $this->dictRepo->clearCache();
        return $result;
    }

    /**
     * 更新字典项
     */
    public function updateItem(int $id, array $data): bool
    {
        $item = $this->itemRepo->find($id);
        if (!$item) {
            throw new BusinessException(lang('business.dict_item_not_found'));
        }

        if (isset($data['value']) && $this->itemRepo->existsValue($item['dictionary_id'], $data['value'], $id)) {
            throw new BusinessException(lang('business.dict_item_value_exists'));
        }

        $updateData = array_filter([
            'label'       => $data['label'] ?? null,
            'value'       => $data['value'] ?? null,
            'tag_type'    => $data['tag_type'] ?? null,
            'description' => $data['description'] ?? null,
            'status'      => isset($data['status']) ? (int)$data['status'] : null,
            'sort'        => isset($data['sort']) ? (int)$data['sort'] : null,
        ], fn($v) => $v !== null);

        $this->dictRepo->clearCache();
        return $this->itemRepo->update($id, $updateData);
    }

    /**
     * 删除字典项
     */
    public function deleteItem(int $id): bool
    {
        $item = $this->itemRepo->find($id);
        if (!$item) {
            throw new BusinessException(lang('business.dict_item_not_found'));
        }
        $this->dictRepo->clearCache();
        return $this->itemRepo->delete($id);
    }
}
