<?php

declare(strict_types=1);

namespace core\plugin\contracts;

interface RouteGenerationStore
{
    public function read(): ?int;

    public function shouldBump(int $before, int $after, bool $installFinal): bool;

    public function bump(): void;
}
