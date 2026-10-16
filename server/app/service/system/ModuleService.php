<?php

declare(strict_types=1);

namespace app\service\system;

use core\base\Service;
use core\module\ModuleCatalog;

final class ModuleService extends Service
{
    /** @return array{modules: list<array<string, mixed>>, errors: list<array{path: string, message: string}>} */
    public function inspect(bool $checkFiles = false): array
    {
        return (new ModuleCatalog())->inspect(base_path(), dirname(base_path()), $checkFiles);
    }
}
