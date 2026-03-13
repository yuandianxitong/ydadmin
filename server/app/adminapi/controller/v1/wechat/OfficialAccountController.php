<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\wechat;

use core\base\Controller;
use app\service\wechat\OfficialAccountService;
use core\attribute\Permission;
use think\Response;

class OfficialAccountController extends Controller
{
    protected OfficialAccountService $service;

    /**
     * 获取自定义菜单
     */
    #[Permission('wechat.official.list')]
    public function getMenu(): Response
    {
        try {
            $result = $this->service->getMenu();
            return $this->success(lang('messages.get_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 创建自定义菜单
     */
    #[Permission('wechat.official.create')]
    public function createMenu(): Response
    {
        try {
            $buttons = $this->request->param('button', []);
            if (empty($buttons)) {
                return $this->error(lang('business.menu_content_required'));
            }

            $result = $this->service->createMenu($buttons);
            return $this->success(lang('messages.create_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除自定义菜单
     */
    #[Permission('wechat.official.delete')]
    public function deleteMenu(): Response
    {
        try {
            $result = $this->service->deleteMenu();
            return $this->success(lang('messages.delete_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 发送模板消息
     */
    #[Permission('wechat.official.send')]
    public function sendTemplate(): Response
    {
        try {
            $openid = (string)$this->request->param('openid', '');
            $templateId = (string)$this->request->param('template_id', '');
            $data = $this->request->param('data', []);
            $url = (string)$this->request->param('url', '');

            if (empty($openid) || empty($templateId)) {
                return $this->error(lang('business.openid_template_required'));
            }

            $result = $this->service->sendTemplateMessage($openid, $templateId, $data, $url);
            return $this->success(lang('messages.send_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取粉丝列表
     */
    #[Permission('wechat.official.list')]
    public function followers(): Response
    {
        try {
            $nextOpenid = (string)$this->request->param('next_openid', '');
            $result = $this->service->getUserList($nextOpenid);
            return $this->success(lang('messages.get_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取用户信息
     */
    #[Permission('wechat.official.list')]
    public function userInfo(): Response
    {
        try {
            $openid = (string)$this->request->param('openid', '');
            if (empty($openid)) {
                return $this->error(lang('business.openid_required'));
            }

            $result = $this->service->getUserInfo($openid);
            return $this->success(lang('messages.get_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
