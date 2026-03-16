<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\wechat;

use core\base\Controller;
use app\service\wechat\OfficialAccountService;
use core\attribute\Permission;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '微信公众号', description: '公众号菜单、模板消息、粉丝管理')]
class OfficialAccountController extends Controller
{
    protected OfficialAccountService $service;

    /**
     * 获取自定义菜单
     */
    #[Permission('wechat.official.list')]
    #[OA\Get(
        path: '/wechat/official/menu',
        summary: '获取公众号自定义菜单',
        security: [['bearerAuth' => []]],
        tags: ['微信公众号'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
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
    #[OA\Post(
        path: '/wechat/official/menu',
        summary: '创建公众号自定义菜单',
        security: [['bearerAuth' => []]],
        tags: ['微信公众号'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['button'],
                properties: [
                    new OA\Property(property: 'button', type: 'array', items: new OA\Items(type: 'object'), description: '菜单按钮列表'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '创建成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
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
    #[OA\Delete(
        path: '/wechat/official/menu',
        summary: '删除公众号自定义菜单',
        security: [['bearerAuth' => []]],
        tags: ['微信公众号'],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
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
    #[OA\Post(
        path: '/wechat/official/template/send',
        summary: '发送公众号模板消息',
        security: [['bearerAuth' => []]],
        tags: ['微信公众号'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['openid', 'template_id'],
                properties: [
                    new OA\Property(property: 'openid', type: 'string', description: '接收者OpenID'),
                    new OA\Property(property: 'template_id', type: 'string', description: '模板ID'),
                    new OA\Property(property: 'data', type: 'object', description: '模板变量数据'),
                    new OA\Property(property: 'url', type: 'string', description: '跳转链接'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '发送成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
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
    #[OA\Get(
        path: '/wechat/official/followers',
        summary: '获取公众号粉丝列表',
        security: [['bearerAuth' => []]],
        tags: ['微信公众号'],
        parameters: [
            new OA\Parameter(name: 'next_openid', in: 'query', description: '下一个OpenID(用于分页)', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
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
    #[OA\Get(
        path: '/wechat/official/user-info',
        summary: '获取公众号用户信息',
        security: [['bearerAuth' => []]],
        tags: ['微信公众号'],
        parameters: [
            new OA\Parameter(name: 'openid', in: 'query', required: true, description: '用户OpenID', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
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
