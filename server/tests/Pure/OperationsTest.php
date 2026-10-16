<?php

declare(strict_types=1);

namespace tests\Pure;

use app\service\operations\PrivateTaskFiles;
use core\support\PayloadFingerprint;
use PHPUnit\Framework\TestCase;

final class OperationsTest extends TestCase
{
    public function test_fingerprint_ignores_object_key_order_but_preserves_types_and_list_order(): void
    {
        $this->assertSame(PayloadFingerprint::hash(['a' => 1, 'b' => ['x' => 2, 'y' => 3]]), PayloadFingerprint::hash(['b' => ['y' => 3, 'x' => 2], 'a' => 1]));
        $this->assertNotSame(PayloadFingerprint::hash(['a' => 1]), PayloadFingerprint::hash(['a' => '1']));
        $this->assertNotSame(PayloadFingerprint::hash([1, 2]), PayloadFingerprint::hash([2, 1]));
    }

    public function test_csv_cells_cannot_execute_spreadsheet_formulas(): void
    {
        foreach (['=1+1', "\t@SUM(A1:A2)", '+cmd', '-cmd'] as $value) {
            $this->assertSame("'" . $value, PrivateTaskFiles::csvCell($value));
        }
        $this->assertSame('normal', PrivateTaskFiles::csvCell('normal'));
    }

    public function test_task_files_reject_paths_before_accessing_storage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PrivateTaskFiles())->path('../secret.csv');
    }
}
