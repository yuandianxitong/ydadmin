<?php

declare(strict_types=1);

namespace core\plugin\contracts;

interface ManifestExtension
{
    /** @return list<string> 宿主专有字段的错误 */
    public function validate(array $manifest): array;

    public function normalize(array $manifest): array;
}
