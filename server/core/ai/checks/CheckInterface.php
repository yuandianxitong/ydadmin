<?php
declare(strict_types=1);

namespace core\ai\checks;

interface CheckInterface
{
    /** 检查的稳定标识，如 php_lint */
    public function name(): string;

    /** @return CheckResult[] */
    public function check(CheckContext $ctx): array;
}
