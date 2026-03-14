<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use core\base\Controller;
use core\attribute\Permission;
use app\service\system\PluginService;
use think\Response;

class PluginController extends Controller
{
    protected PluginService $pluginService;

    /**
     * 插件列表
     */
    #[Permission('system.plugin.list')]
    public function index(): Response
    {
        $list = $this->pluginService->getPluginList();
        return $this->success(lang('messages.get_success'), $list);
    }

    /**
     * 安装插件
     */
    #[Permission('system.plugin.install')]
    public function install(): Response
    {
        $name = $this->request->param('name');
        $this->pluginService->install($name);
        return $this->success('插件安装成功');
    }

    /**
     * 卸载插件
     */
    #[Permission('system.plugin.uninstall')]
    public function uninstall(): Response
    {
        $name = $this->request->param('name');
        $this->pluginService->uninstall($name);
        return $this->success('插件卸载成功');
    }

    /**
     * 启用插件
     */
    #[Permission('system.plugin.enable')]
    public function enable(): Response
    {
        $name = $this->request->param('name');
        $this->pluginService->enable($name);
        return $this->success('插件启用成功');
    }

    /**
     * 禁用插件
     */
    #[Permission('system.plugin.disable')]
    public function disable(): Response
    {
        $name = $this->request->param('name');
        $this->pluginService->disable($name);
        return $this->success('插件禁用成功');
    }

    /**
     * 上传插件
     */
    #[Permission('system.plugin.upload')]
    public function upload(): Response
    {
        $file = $this->request->file('file');
        if (!$file) {
            return $this->error('请选择文件');
        }
        $info = $this->pluginService->uploadPlugin($file);
        return $this->success('插件上传成功', $info);
    }

    /**
     * 删除插件
     */
    #[Permission('system.plugin.delete')]
    public function delete(): Response
    {
        $name = $this->request->param('name');
        $this->pluginService->deletePlugin($name);
        return $this->success('插件删除成功');
    }
}
