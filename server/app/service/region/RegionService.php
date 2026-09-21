<?php

declare(strict_types=1);

namespace app\service\region;

use app\repository\region\RegionRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use core\exception\ValidationException;
use DI\Attribute\Inject;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * 地区。
 *
 * 只调 Repository：查询条件在 RegionRepository。这一层不直接调用数据库门面。
 * 写操作一律包 runInTransaction()。
 *
 * 请求里的 level 丢掉，按父级重算（根=1）。code 重复 / 撞唯一索引 → 422 errors.code。
 */
class RegionService extends Service
{
    /** @var list<string> */
    private const WRITE_FIELDS = ['parent_id', 'name', 'code', 'sort', 'status'];

    #[Inject]
    protected RegionRepository $regionRepository;

    /**
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getList(array $params, int $page, int $limit): array
    {
        return $this->regionRepository->getSearchList($params, $page, $limit);
    }

    /**
     * 启用地区树：[{value,label,children?}]。
     *
     * @return list<array{value: int, label: string, children?: list<array<string, mixed>>}>
     */
    public function getTree(): array
    {
        return $this->regionRepository->buildValueLabelTree($this->regionRepository->listEnabled());
    }

    /**
     * 指定父级下的启用子级（完整行）。
     *
     * @return list<array<string, mixed>>
     */
    public function getChildren(int $parentId): array
    {
        return $this->regionRepository->getByParentId($parentId);
    }

    /** @return array<string, mixed> */
    public function getDetail(int $id): array
    {
        return $this->findOrFail($id);
    }

    /**
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $parentId = (int) ($data['parent_id'] ?? 0);
        $this->assertParentValid($parentId);

        $code = (string) $data['code'];
        if ($this->regionRepository->existsByCode($code)) {
            throw $this->codeTaken();
        }

        $row = [
            'parent_id' => $parentId,
            'name'      => $data['name'],
            'code'      => $code,
            'level'     => $this->levelOfParent($parentId),
            'sort'      => (int) ($data['sort'] ?? 0),
            'status'    => (int) ($data['status'] ?? 1),
        ];

        try {
            return $this->runInTransaction(fn (): array => $this->regionRepository->create($row));
        } catch (UniqueConstraintViolationException) {
            throw $this->codeTaken();
        }
    }

    /**
     * 局部更新：只写传了的字段，值为 null 的丢弃；丢掉 level，按父级重算。
     *
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     */
    public function update(int $id, array $data): void
    {
        $this->findOrFail($id);

        if (array_key_exists('parent_id', $data)) {
            $this->assertParentValid((int) $data['parent_id'], $id);
        }
        if (isset($data['code']) && $this->regionRepository->existsByCode((string) $data['code'], $id)) {
            throw $this->codeTaken();
        }

        $update = array_filter(
            array_intersect_key($data, array_flip(self::WRITE_FIELDS)),
            static fn ($value) => $value !== null
        );
        if (array_key_exists('parent_id', $update)) {
            $update['parent_id'] = (int) $update['parent_id'];
            $update['level'] = $this->levelOfParent((int) $update['parent_id']);
        }
        if ($update === []) {
            return;
        }

        try {
            $this->runInTransaction(function () use ($id, $update): void {
                $this->regionRepository->update($id, $update);
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->codeTaken();
        }
    }

    public function delete(int $id): void
    {
        $this->findOrFail($id);

        if ($this->regionRepository->hasChildren($id)) {
            throw new BusinessException(lang('region.has_children'));
        }

        $this->runInTransaction(function () use ($id): void {
            $this->regionRepository->delete($id);
        });
    }

    private function assertParentValid(int $parentId, ?int $selfId = null): void
    {
        if ($parentId === 0) {
            return;
        }
        if ($selfId !== null && $parentId === $selfId) {
            throw new BusinessException(lang('region.parent_invalid'));
        }
        if ($this->regionRepository->find($parentId) === null) {
            throw new BusinessException(lang('region.parent_invalid'));
        }
        if ($selfId !== null && in_array($parentId, $this->regionRepository->descendantIds($selfId), true)) {
            throw new BusinessException(lang('region.parent_invalid'));
        }
    }

    private function levelOfParent(int $parentId): int
    {
        if ($parentId === 0) {
            return 1;
        }
        $parent = $this->regionRepository->find($parentId);
        if ($parent === null) {
            throw new BusinessException(lang('region.parent_invalid'));
        }

        return (int) $parent['level'] + 1;
    }

    /** @return array<string, mixed> */
    private function findOrFail(int $id): array
    {
        return $this->regionRepository->find($id)
            ?? throw new NotFoundException(lang('region.not_found'));
    }

    private function codeTaken(): ValidationException
    {
        return new ValidationException(['code' => lang('region.code_exists')]);
    }
}
