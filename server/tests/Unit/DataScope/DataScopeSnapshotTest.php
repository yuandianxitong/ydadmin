<?php

declare(strict_types=1);

namespace tests\Unit\DataScope;

use core\datascope\DataScopeSnapshot;
use tests\TestCase;

final class DataScopeSnapshotTest extends TestCase
{
    /** @param list<int> $deptIds */
    private function snap(bool $all, array $deptIds = [], bool $self = false): DataScopeSnapshot
    {
        return new DataScopeSnapshot($all, $deptIds, $self, 1);
    }

    public function test_all_covers_everything(): void
    {
        $all = $this->snap(true);

        $this->assertTrue($all->covers($this->snap(true)));
        $this->assertTrue($all->covers($this->snap(false, [1, 2, 3], true)));
    }

    public function test_only_all_covers_all(): void
    {
        $this->assertFalse($this->snap(false, [1, 2, 3], true)->covers($this->snap(true)));
    }

    public function test_dept_ids_must_be_a_subset(): void
    {
        $mine = $this->snap(false, [2, 3]);

        $this->assertTrue($mine->covers($this->snap(false, [3])));
        $this->assertTrue($mine->covers($this->snap(false, [3, 2])));
        $this->assertTrue($mine->covers($this->snap(false, [])));
        $this->assertFalse($mine->covers($this->snap(false, [3, 4])));
        $this->assertFalse($this->snap(false, [])->covers($this->snap(false, [2])));
    }

    public function test_self_is_ignored(): void
    {
        // 「仅本人」是对方自己的数据，不构成越权
        $this->assertTrue($this->snap(false, [])->covers($this->snap(false, [], true)));
        $this->assertTrue($this->snap(false, [2], true)->covers($this->snap(false, [2])));
    }
}
