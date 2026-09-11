<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\MenuRepository;
use core\base\Service;
use DI\Attribute\Inject;

class MenuService extends Service
{
    #[Inject]
    protected MenuRepository $menuRepository;

    /**
     * 前端路由树（契约 §4.3）：auth/info 与 menu/routes 共用，保证两者一致。
     *
     * @param array<int, int> $menuIds
     * @return array<int, array<string, mixed>>
     */
    public function getFrontendRoutes(array $menuIds): array
    {
        return $this->menuRepository->getFrontendRoutes($menuIds);
    }

    /**
     * @param array<int, int> $menuIds
     * @return list<string>
     */
    public function getButtonPermissions(array $menuIds): array
    {
        return $this->menuRepository->getButtonPermissionsByMenuIds($menuIds);
    }
}
