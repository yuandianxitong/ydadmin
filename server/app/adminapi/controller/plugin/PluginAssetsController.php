<?php

declare(strict_types=1);

namespace app\adminapi\controller\plugin;

use app\adminapi\controller\AuthenticatedController;
use app\service\plugin\PluginAssetCatalog;
use core\auth\PermissionSkip;
use DI\Attribute\Inject;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;

#[RouteGroup('/adminapi/plugin')]
final class PluginAssetsController extends AuthenticatedController
{
    #[Inject]
    protected PluginAssetCatalog $assets;

    #[PermissionSkip]
    #[Get('/assets')]
    public function index(): Response
    {
        return $this->success($this->assets->adminEntries());
    }
}
