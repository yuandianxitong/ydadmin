<?php

declare(strict_types=1);

namespace tests\Pure;

use core\generator\ThreeWayMerge;
use PHPUnit\Framework\TestCase;

final class ThreeWayMergeTest extends TestCase
{
    public function test_disjoint_generated_and_custom_changes_are_both_retained(): void
    {
        $base = "a\nb\nc\nd\n";
        $this->assertSame("A\nb\nc\nD\ncustom\n", (new ThreeWayMerge())->merge($base, "a\nb\nc\nD\ncustom\n", "A\nb\nc\nd\n"));
    }

    public function test_overlapping_changes_and_competing_insertions_are_conflicts(): void
    {
        $merge = new ThreeWayMerge();
        $this->assertNull($merge->merge("a\nb\n", "a\nours\n", "a\ntheirs\n"));
        $this->assertNull($merge->merge("a\nb\n", "a\nours\nb\n", "a\ntheirs\nb\n"));
    }

    public function test_identical_changes_are_not_duplicated_and_deletions_keep_line_endings(): void
    {
        $merge = new ThreeWayMerge();
        $this->assertSame("a\nx\nb\n", $merge->merge("a\nb\n", "a\nx\nb\n", "a\nx\nb\n"));
        $this->assertSame("A\r\nc", $merge->merge("a\r\nb\r\nc", "A\r\nb\r\nc", "a\r\nc"));
        $this->assertSame('custom', $merge->merge('', 'custom', ''));
    }

    public function test_unbounded_diff_work_is_rejected_without_losing_the_current_file(): void
    {
        $this->assertNull((new ThreeWayMerge())->merge(str_repeat("base\n", 1000), str_repeat("custom\n", 1000), str_repeat("new\n", 1000)));
    }
}
