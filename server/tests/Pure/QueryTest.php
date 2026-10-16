<?php

declare(strict_types=1);

namespace tests\Pure;

use core\query\Pagination;
use core\query\QueryInput;
use core\query\SortOrder;
use PHPUnit\Framework\TestCase;

final class QueryTest extends TestCase
{
    public function test_pagination_aliases_precedence_and_limits(): void
    {
        $page = Pagination::fromArray(['page_no' => '2', 'page_size' => '999']);
        $this->assertSame([2, 100], [$page->page, $page->limit]);
        $page = Pagination::fromArray(['page' => 0, 'page_no' => 4, 'limit' => -2]);
        $this->assertSame([1, 1], [$page->page, $page->limit]);
        $page = Pagination::fromArray(['page' => ['2'], 'limit' => 'invalid'], 20);
        $this->assertSame([1, 20], [$page->page, $page->limit]);
        $page = Pagination::fromArray(['page' => '', 'limit' => ' ', 'page_no' => 3, 'page_size' => 25]);
        $this->assertSame([3, 25], [$page->page, $page->limit]);
    }

    public function test_optional_filter_normalization_preserves_zero_and_invalid_types(): void
    {
        $this->assertSame(
            ['keyword' => 'name', 'status' => '0', 'type' => ['bad'], 'extra' => '  untouched  '],
            QueryInput::normalize(['keyword' => ' name ', 'status' => '0', 'type' => ['bad'], 'start' => '', 'end' => null, 'extra' => '  untouched  '], ['keyword', 'status', 'type', 'start', 'end']),
        );
    }

    public function test_sorting_is_multi_column_and_qualified_by_repository_only(): void
    {
        $this->assertSame([['sort', 'asc'], ['id', 'desc']], SortOrder::parse('sort, id DESC', ['sort', 'id']));
        $this->expectException(\InvalidArgumentException::class);
        SortOrder::parse('id desc; DROP TABLE admins', ['id']);
    }

    public function test_unlisted_sort_column_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SortOrder::parse('password desc', ['id']);
    }

    public function test_date_ranges_allow_one_side_but_reject_reversed_bounds(): void
    {
        QueryInput::assertDateRanges(['start' => '2026-10-01'], [['start', 'end']]);
        $this->expectException(\core\exception\ValidationException::class);
        QueryInput::assertDateRanges(['start' => '2026-10-10', 'end' => '2026-10-01'], [['start', 'end']]);
    }
}
