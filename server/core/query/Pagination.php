<?php

declare(strict_types=1);

namespace core\query;

/** 已验证分页输入的值对象，同时保留旧接口的边界截断规则。 */
final readonly class Pagination
{
    private function __construct(public int $page, public int $limit)
    {
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input, int $defaultLimit = 15, int $maxLimit = 100): self
    {
        $input = QueryInput::normalize($input, ['page', 'limit', 'page_no', 'page_size']);
        $page = self::integer($input['page'] ?? $input['page_no'] ?? 1, 1);
        $limit = self::integer($input['limit'] ?? $input['page_size'] ?? $defaultLimit, $defaultLimit);

        return new self(max(1, $page), min(max(1, $maxLimit), max(1, $limit)));
    }

    private static function integer(mixed $value, int $fallback): int
    {
        if (!is_int($value) && !is_string($value)) {
            return $fallback;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT);

        return $parsed === false ? $fallback : $parsed;
    }
}
