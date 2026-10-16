<?php

declare(strict_types=1);

namespace core\config;

final class ConfigSnapshot implements ConfigValueReader
{
    /** @param array<string, mixed> $values */
    public function __construct(private readonly array $values)
    {
    }

    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }
}
