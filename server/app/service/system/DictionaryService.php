<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\DictionaryItemRepository;
use app\repository\system\DictionaryRepository;
use core\base\Service;
use core\exception\BusinessException;
use DI\Attribute\Inject;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * 数据字典与字典项（契约 §2.6）。不受数据权限约束。
 *
 * options 缓存（dict.{code}）：字典或字典项的每一次写入都在事务内经 afterCommit 清掉受影响的编码；
 * 字典改编码时新旧两个编码都清——旧编码缓存着旧项，新编码可能缓存着「不存在」的 []。
 *
 * 唯一性：先按未删除行预检查，给出业务提示。与软删行冲突（唯一索引对软删行同样生效），或并发写入撞上
 * 唯一索引时，捕获 UniqueConstraintViolationException 转成同一条业务错误，不返回 500。
 */
class DictionaryService extends Service
{
    /**
     * batch-options 一次最多接受的编码数。接口是 PermissionSkip，每个未知但合法的编码都要查一次库、
     * 再写一条 7200 秒的负缓存，不设上限就能靠一次请求把 Redis（还存着 token 版本号与黑名单）撑起来。
     */
    public const MAX_BATCH_CODES = 50;

    #[Inject]
    protected DictionaryRepository $dictionaryRepository;

    #[Inject]
    protected DictionaryItemRepository $dictionaryItemRepository;

    /**
     * @param array<string, mixed> $params keyword（name/code 模糊）、status
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getDictionaryList(array $params, int $page, int $limit): array
    {
        return $this->dictionaryRepository->getListWithItemCount($params, $page, $limit);
    }

    /**
     * 详情：字典行 + 全部字典项（不分状态）。不存在时抛 business.dict_not_found（code 400）。
     *
     * @return array<string, mixed>
     */
    public function getDictionaryDetail(int $id): array
    {
        return $this->dictionaryRepository->findWithItems($id) ?? throw new BusinessException(lang('business.dict_not_found'));
    }

    /**
     * @param array<string, mixed> $data 已校验：name、code 必有
     * @return array<string, mixed>
     */
    public function createDictionary(array $data): array
    {
        $code = (string) $data['code'];
        if ($this->dictionaryRepository->existsCode($code)) {
            throw new BusinessException(lang('business.dict_code_exists'));
        }
        $row = [
            'name'        => $data['name'],
            'code'        => $code,
            'description' => (string) ($data['description'] ?? ''),
            'status'      => (int) ($data['status'] ?? 1),
            'sort'        => (int) ($data['sort'] ?? 0),
        ];

        try {
            return $this->runInTransaction(function () use ($row, $code): array {
                $dictionary = $this->dictionaryRepository->create($row);
                // 该编码此前可能被 options 缓存成了「不存在」的 []
                $this->afterCommit(fn () => $this->dictionaryRepository->forgetOptions([$code]));

                return $dictionary;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException(lang('business.dict_code_exists'));
        }
    }

    /** @param array<string, mixed> $data 字段均可选 */
    public function updateDictionary(int $id, array $data): void
    {
        $dictionary = $this->findDictionaryOrFail($id);
        $oldCode = (string) $dictionary['code'];
        $newCode = isset($data['code']) ? (string) $data['code'] : $oldCode;
        if ($newCode !== $oldCode && $this->dictionaryRepository->existsCode($newCode, $id)) {
            throw new BusinessException(lang('business.dict_code_exists'));
        }
        $update = array_filter(
            array_intersect_key($data, array_flip(['name', 'code', 'description', 'status', 'sort'])),
            static fn ($value) => $value !== null
        );
        if ($update === []) {
            return;
        }

        try {
            $this->runInTransaction(function () use ($id, $update, $oldCode, $newCode): void {
                $this->dictionaryRepository->update($id, $update);
                $this->afterCommit(fn () => $this->dictionaryRepository->forgetOptions([$oldCode, $newCode]));
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException(lang('business.dict_code_exists'));
        }
    }

    /** 删除字典：同一事务内先软删它的全部字典项，再软删字典（契约 §2.6：直接级联，不做「有字典项不可删」的限制）。 */
    public function deleteDictionary(int $id): void
    {
        $code = (string) $this->findDictionaryOrFail($id)['code'];

        $this->runInTransaction(function () use ($id, $code): void {
            $this->dictionaryItemRepository->deleteWhere(['dictionary_id' => $id]);
            $this->dictionaryRepository->delete($id);
            $this->afterCommit(fn () => $this->dictionaryRepository->forgetOptions([$code]));
        });
    }

    /**
     * 批量删除：同一事务内逐条级联删除，任一 id 不存在则整体回滚（与角色批量删除一致）。重复 id 先去重。
     *
     * @param array<int, int> $ids
     */
    public function batchDeleteDictionaries(array $ids): void
    {
        $this->runInTransaction(function () use ($ids): void {
            foreach (array_values(array_unique($ids)) as $id) {
                $this->deleteDictionary($id);
            }
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function getOptionsByCode(string $code): array
    {
        return $this->dictionaryRepository->getOptionsByCode($code);
    }

    /**
     * 按编码批量取选项（契约 §2.6：逗号分隔的字符串或数组）。去空白、去重；未知或非法编码返回 []。
     *
     * @param array<int|string, mixed>|string $codes
     * @return array<int|string, array<int, array<string, mixed>>>
     */
    public function getOptionsByCodes(array|string $codes): array
    {
        $result = [];
        foreach (is_array($codes) ? $codes : explode(',', $codes) as $code) {
            $code = is_scalar($code) ? trim((string) $code) : '';
            if ($code !== '' && !array_key_exists($code, $result)) {
                $result[$code] = $this->dictionaryRepository->getOptionsByCode($code);
            }
        }

        return $result;
    }

    /**
     * 某字典的全部字典项（任意状态）。字典不存在时抛 business.dict_not_found。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getItemList(int $dictionaryId): array
    {
        $this->findDictionaryOrFail($dictionaryId);

        return $this->dictionaryItemRepository->getByDictionaryId($dictionaryId);
    }

    /**
     * @param array<string, mixed> $data 已校验：dictionary_id、label、value 必有
     * @return array<string, mixed>
     */
    public function createItem(array $data): array
    {
        $dictionaryId = (int) $data['dictionary_id'];
        $code = (string) $this->findDictionaryOrFail($dictionaryId)['code'];
        $value = (string) $data['value'];
        if ($this->dictionaryItemRepository->existsValue($dictionaryId, $value)) {
            throw new BusinessException(lang('business.dict_item_value_exists'));
        }
        $row = [
            'dictionary_id' => $dictionaryId,
            'label'         => $data['label'],
            'value'         => $value,
            'tag_type'      => (string) ($data['tag_type'] ?? ''),
            'description'   => (string) ($data['description'] ?? ''),
            'status'        => (int) ($data['status'] ?? 1),
            'sort'          => (int) ($data['sort'] ?? 0),
        ];

        try {
            return $this->runInTransaction(function () use ($row, $code): array {
                $item = $this->dictionaryItemRepository->create($row);
                $this->afterCommit(fn () => $this->dictionaryRepository->forgetOptions([$code]));

                return $item;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException(lang('business.dict_item_value_exists'));
        }
    }

    /** @param array<string, mixed> $data 字段均可选；不含 dictionary_id（字典项不能改挂到别的字典） */
    public function updateItem(int $id, array $data): void
    {
        $item = $this->findItemOrFail($id);
        $dictionaryId = (int) $item['dictionary_id'];
        if (isset($data['value']) && (string) $data['value'] !== (string) $item['value']
            && $this->dictionaryItemRepository->existsValue($dictionaryId, (string) $data['value'], $id)) {
            throw new BusinessException(lang('business.dict_item_value_exists'));
        }
        $update = array_filter(
            array_intersect_key($data, array_flip(['label', 'value', 'tag_type', 'description', 'status', 'sort'])),
            static fn ($value) => $value !== null
        );
        if ($update === []) {
            return;
        }
        $codes = $this->codesOf($dictionaryId);

        try {
            $this->runInTransaction(function () use ($id, $update, $codes): void {
                $this->dictionaryItemRepository->update($id, $update);
                $this->afterCommit(fn () => $this->dictionaryRepository->forgetOptions($codes));
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException(lang('business.dict_item_value_exists'));
        }
    }

    public function deleteItem(int $id): void
    {
        $codes = $this->codesOf((int) $this->findItemOrFail($id)['dictionary_id']);

        $this->runInTransaction(function () use ($id, $codes): void {
            $this->dictionaryItemRepository->delete($id);
            $this->afterCommit(fn () => $this->dictionaryRepository->forgetOptions($codes));
        });
    }

    /** @return array<string, mixed> */
    private function findDictionaryOrFail(int $id): array
    {
        return $this->dictionaryRepository->find($id) ?? throw new BusinessException(lang('business.dict_not_found'));
    }

    /** @return array<string, mixed> */
    private function findItemOrFail(int $id): array
    {
        return $this->dictionaryItemRepository->find($id) ?? throw new BusinessException(lang('business.dict_item_not_found'));
    }

    /**
     * 字典项所属字典的编码（用于清 options 缓存；字典已不存在时无缓存可清）。
     *
     * @return list<string>
     */
    private function codesOf(int $dictionaryId): array
    {
        $dictionary = $this->dictionaryRepository->find($dictionaryId);

        return $dictionary === null ? [] : [(string) $dictionary['code']];
    }
}
