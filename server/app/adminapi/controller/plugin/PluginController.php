<?php

declare(strict_types=1);

namespace app\adminapi\controller\plugin;

use app\adminapi\controller\AuthenticatedController;
use app\service\plugin\PluginService;
use core\auth\Permission;
use core\exception\BusinessException;
use DI\Attribute\Inject;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;
use Webman\Http\UploadFile;

/**
 * 站点插件管理。
 *
 *   GET  /adminapi/plugin/list       index      plugin.list
 *   POST /adminapi/plugin/install    install    plugin.install
 *   POST /adminapi/plugin/enable     enable     plugin.enable
 *   POST /adminapi/plugin/disable    disable    plugin.disable
 *   POST /adminapi/plugin/upgrade    upgrade    plugin.upgrade
 *   POST /adminapi/plugin/uninstall  uninstall  plugin.uninstall
 *   POST /adminapi/plugin/purge      purge      plugin.purge
 */
#[RouteGroup('/adminapi/plugin')]
final class PluginController extends AuthenticatedController
{
    #[Inject]
    protected PluginService $plugins;

    #[Get('/list')]
    #[Permission('plugin.list')]
    public function index(): Response
    {
        return $this->success($this->plugins->catalog());
    }

    #[Post('/install')]
    #[Permission('plugin.install')]
    public function install(Request $request): Response
    {
        $file = $request->file('file');
        if ($file instanceof UploadFile && $file->isValid()) {
            return $this->success($this->plugins->installFromZip($file->getPathname()), '插件已安装。插件菜单会在重新进入后台后出现');
        }
        $data = $this->validate($this->body($request), $this->installRules());

        return $this->success($this->plugins->install($data['code']), '插件已安装。插件菜单会在重新进入后台后出现');
    }

    #[Post('/enable')]
    #[Permission('plugin.enable')]
    public function enable(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->enableRules());

        return $this->success($this->plugins->enable($data['code']), '插件已启用。插件菜单会在重新进入后台后出现');
    }

    #[Post('/disable')]
    #[Permission('plugin.disable')]
    public function disable(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->disableRules());

        return $this->success($this->plugins->disable($data['code']));
    }

    #[Post('/upgrade')]
    #[Permission('plugin.upgrade')]
    public function upgrade(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->upgradeRules());
        $file = $request->file('file');
        if (!$file instanceof UploadFile || !$file->isValid()) {
            throw new BusinessException('请上传 zip 升级包', 422);
        }

        return $this->success($this->plugins->upgrade($data['code'], $file->getPathname()), '插件已升级。插件菜单会在重新进入后台后出现');
    }

    #[Post('/uninstall')]
    #[Permission('plugin.uninstall')]
    public function uninstall(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->uninstallRules());

        return $this->success($this->plugins->uninstall($data['code']), '插件已卸载，数据表和目录仍保留');
    }

    #[Post('/purge')]
    #[Permission('plugin.purge')]
    public function purge(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->purgeRules());

        return $this->success($this->plugins->uninstall($data['code'], true), '插件数据已清除，目录仍保留');
    }

    /** @return array<string, string> */
    private function installRules(): array
    {
        return ['code' => 'required|string|min:2|max:64'];
    }

    /** @return array<string, string> */
    private function enableRules(): array
    {
        return ['code' => 'required|string|min:2|max:64'];
    }

    /** @return array<string, string> */
    private function disableRules(): array
    {
        return ['code' => 'required|string|min:2|max:64'];
    }

    /** @return array<string, string> */
    private function upgradeRules(): array
    {
        return ['code' => 'required|string|min:2|max:64'];
    }

    /** @return array<string, string> */
    private function uninstallRules(): array
    {
        return ['code' => 'required|string|min:2|max:64'];
    }

    /** @return array<string, string> */
    private function purgeRules(): array
    {
        return ['code' => 'required|string|min:2|max:64'];
    }
}
