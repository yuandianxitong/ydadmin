<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use app\service\system\SystemConfigService;
use app\adminapi\validate\v1\system\SystemConfigValidate;
use core\base\Controller;
use think\Response;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '系统配置', description: '系统配置的分组查询、更新')]
class SystemConfigController extends Controller
{
    protected SystemConfigService $systemConfigService;

    #[Permission('system.config.list')]
    #[OA\Get(
        path: '/system/config',
        summary: '获取系统配置列表（分组）',
        security: [['bearerAuth' => []]],
        tags: ['系统配置'],
        parameters: [
            new OA\Parameter(name: 'group', in: 'query', description: '配置分组', schema: new OA\Schema(type: 'string', default: 'basic')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '请求失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function index(): Response
    {
        $group = $this->request->param('group', 'basic');

        try {
            $configs = $this->systemConfigService->getConfigsByGroup($group);
            return $this->success(lang('messages.config_list_success'), $configs);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/system/config/groups',
        summary: '获取所有配置分组',
        security: [['bearerAuth' => []]],
        tags: ['系统配置'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '请求失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function groups(): Response
    {
        try {
            $groups = $this->systemConfigService->getConfigGroups();
            return $this->success(lang('messages.config_group_success'), $groups);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[Permission('system.config.list')]
    #[OA\Get(
        path: '/system/config/{id}',
        summary: '获取单个配置',
        security: [['bearerAuth' => []]],
        tags: ['系统配置'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '配置ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '请求失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function show(): Response
    {
        $id = (int)$this->request->param('id');

        try {
            $config = $this->systemConfigService->getConfigById($id);
            return $this->success(lang('messages.config_get_success'), $config);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[Permission('system.config.update')]
    #[OA\Put(
        path: '/system/config/{id}',
        summary: '更新配置',
        security: [['bearerAuth' => []]],
        tags: ['系统配置'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '配置ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['config_value'],
                properties: [
                    new OA\Property(property: 'config_value', type: 'string', description: '配置值'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '验证失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function update(): Response
    {
        $id = (int)$this->request->param('id');
        $data = $this->request->only(['config_value']);

        try {
            // 验证数据
            $validate = new SystemConfigValidate();
            if (!$validate->scene('update')->check($data)) {
                return $this->error($validate->getError());
            }

            $result = $this->systemConfigService->updateConfig($id, $data);
            return $this->success(lang('messages.config_update_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[Permission('system.config.update')]
    #[OA\Post(
        path: '/system/config/batch-update',
        summary: '批量更新配置',
        security: [['bearerAuth' => []]],
        tags: ['系统配置'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['configs'],
                properties: [
                    new OA\Property(property: 'configs', type: 'array', items: new OA\Items(type: 'object'), description: '配置列表'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '验证失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function batchUpdate(): Response
    {
        $configs = $this->request->param('configs', []);

        try {
            // 验证数据
            $validate = new SystemConfigValidate();
            if (!$validate->scene('batchUpdate')->check(['configs' => $configs])) {
                return $this->error($validate->getError());
            }

            $result = $this->systemConfigService->batchUpdateConfigs($configs);
            return $this->success(lang('messages.config_update_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/system/config/global',
        summary: '获取全局配置（用于前端应用初始化）',
        security: [['bearerAuth' => []]],
        tags: ['系统配置'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '请求失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function global(): Response
    {
        try {
            $configs = $this->systemConfigService->getGlobalConfigs();
            return $this->success(lang('messages.global_config_success'), $configs);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
