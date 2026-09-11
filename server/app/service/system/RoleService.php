<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\RoleRepository;
use core\base\Service;
use DI\Attribute\Inject;

class RoleService extends Service
{
    #[Inject]
    protected RoleRepository $roleRepository;

    /** @return list<array{id: int, name: string, title: string}> */
    public function getAllRoleOptions(): array
    {
        return $this->roleRepository->getAllEnabled();
    }
}
