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

    /**
     * 本范围是否覆盖 $other（M1b 防提权：授出的范围不能超出自己）。
     * all 覆盖一切；非 all 覆盖不了 all；否则 $other 的部门必须是本范围部门的子集。
     * 忽略 self：「仅本人」是对方自己的数据，不构成越权。
     */
    public function covers(self $other): bool
    {
        if ($this->all) {
            return true;
        }
        if ($other->all) {
            return false;
        }

        return array_diff($other->deptIds, $this->deptIds) === [];
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
