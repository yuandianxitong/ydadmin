<?php

declare(strict_types=1);

namespace core\datascope;

/** 某个管理员合并全部启用角色后的数据范围（spec §5.1）。 */
final readonly class DataScopeSnapshot
{
    /** @param list<int> $deptIds */
    public function __construct(
        public bool $all,
        public array $deptIds,
        public bool $self,
        public int $adminId,
    ) {
    }

    /** @return array{all: bool, dept_ids: list<int>, self: bool, admin_id: int} */
    public function toArray(): array
    {
        return ['all' => $this->all, 'dept_ids' => $this->deptIds, 'self' => $this->self, 'admin_id' => $this->adminId];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (bool) ($data['all'] ?? false),
            array_values(array_map('intval', (array) ($data['dept_ids'] ?? []))),
            (bool) ($data['self'] ?? false),
            (int) ($data['admin_id'] ?? 0),
        );
    }
}
