<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use app\service\system\MenuService;
use app\service\system\AdminService;
use app\adminapi\validate\v1\system\MenuValidate;
use core\base\Controller;
use think\Response;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '菜单管理', description: '菜单的增删改查、排序、路由')]
class MenuController extends Controller
{
    protected MenuService $menuService;
    protected AdminService $adminService;

    #[Permission('system.menu.list')]
    #[OA\Get(
        path: '/system/menu',
        summary: '获取菜单树',
        security: [['bearerAuth' => []]],
        tags: ['菜单管理'],
        parameters: [
            new OA\Parameter(name: 'only_enabled', in: 'query', description: '仅显示启用菜单', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function index(): Response
    {
        $onlyEnabled = $this->request->param('only_enabled', false);
        $result = $this->menuService->getMenuTree(!$onlyEnabled);

        return $this->success(lang('messages.get_success'), $result);
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/system/menu/options',
        summary: '获取菜单选项树（下拉用）',
        security: [['bearerAuth' => []]],
        tags: ['菜单管理'],
        parameters: [
            new OA\Parameter(name: 'exclude_id', in: 'query', description: '排除的菜单ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function options(): Response
    {
        $excludeId = (int) $this->request->param('exclude_id', 0);
        $result = $this->menuService->getMenuOptions($excludeId);

        return $this->success(lang('messages.get_success'), $result);
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/system/menu/routes',
        summary: '获取前端路由菜单',
        security: [['bearerAuth' => []]],
        tags: ['菜单管理'],
        responses: [new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function routes(): Response
    {
        $adminInfo = $this->adminService->getAdminInfo($this->getUserId());
        $menuIds = $adminInfo['menu_ids'] ?? [];

        $result = $this->menuService->getFrontendRoutes($menuIds);

        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('system.menu.create')]
    #[OA\Post(
        path: '/system/menu',
        summary: '创建菜单',
        security: [['bearerAuth' => []]],
        tags: ['菜单管理'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['title', 'type'],
            properties: [
                new OA\Property(property: 'parent_id', type: 'integer', description: '父级ID', default: 0),
                new OA\Property(property: 'type', type: 'integer', description: '类型(1目录 2菜单 3按钮)', enum: [1, 2, 3]),
                new OA\Property(property: 'title', type: 'string', description: '标题'),
                new OA\Property(property: 'name', type: 'string', description: '路由名称'),
                new OA\Property(property: 'path', type: 'string', description: '路由路径'),
                new OA\Property(property: 'component', type: 'string', description: '组件路径'),
                new OA\Property(property: 'icon', type: 'string', description: '图标'),
                new OA\Property(property: 'permission', type: 'string', description: '权限标识'),
                new OA\Property(property: 'sort', type: 'integer', description: '排序'),
                new OA\Property(property: 'is_hidden', type: 'integer', description: '是否隐藏', enum: [0, 1]),
                new OA\Property(property: 'is_cache', type: 'integer', description: '是否缓存', enum: [0, 1]),
                new OA\Property(property: 'status', type: 'integer', enum: [0, 1]),
            ]
        )),
        responses: [new OA\Response(response: 200, description: '创建成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function store(): Response
    {
        $data = $this->request->post();
        $this->validate($data, MenuValidate::class, [], 'create');

        $data['created_by'] = $this->getUserId();
        $result = $this->menuService->createMenu($data);

        return $this->success(lang('messages.create_success'), $result);
    }

    #[Permission('system.menu.update')]
    #[OA\Put(
        path: '/system/menu/{id}',
        summary: '更新菜单',
        security: [['bearerAuth' => []]],
        tags: ['菜单管理'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'title', type: 'string'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'path', type: 'string'),
            new OA\Property(property: 'component', type: 'string'),
            new OA\Property(property: 'icon', type: 'string'),
            new OA\Property(property: 'sort', type: 'integer'),
            new OA\Property(property: 'status', type: 'integer', enum: [0, 1]),
        ])),
        responses: [new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function update(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->post();
        $this->validate($data, MenuValidate::class, [], 'update');

        $data['updated_by'] = $this->getUserId();
        $this->menuService->updateMenu($id, $data);

        return $this->success(lang('messages.update_success'));
    }

    #[Permission('system.menu.delete')]
    #[OA\Delete(
        path: '/system/menu/{id}',
        summary: '删除菜单',
        security: [['bearerAuth' => []]],
        tags: ['菜单管理'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->menuService->deleteMenu($id);

        return $this->success(lang('messages.delete_success'));
    }

    #[Permission('system.menu.delete')]
    #[OA\Post(
        path: '/system/menu/batch-delete',
        summary: '批量删除菜单',
        security: [['bearerAuth' => []]],
        tags: ['菜单管理'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['ids'],
            properties: [new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'))]
        )),
        responses: [new OA\Response(response: 200, description: '批量删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function batchDelete(): Response
    {
        $ids = $this->request->post('ids', []);

        if (empty($ids)) {
            return $this->error(lang('business.please_select_menu'));
        }

        $this->menuService->batchDeleteMenu($ids);

        return $this->success(lang('messages.batch_delete_success'));
    }

    #[Permission('system.menu.update')]
    #[OA\Put(
        path: '/system/menu/{id}/status',
        summary: '更新菜单状态',
        security: [['bearerAuth' => []]],
        tags: ['菜单管理'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'status', type: 'integer', enum: [0, 1]),
        ])),
        responses: [new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function status(): Response
    {
        $id = (int) $this->request->param('id');
        $status = (int) $this->request->post('status');
        $this->menuService->updateMenu($id, ['status' => $status]);

        return $this->success(lang('messages.update_success'));
    }

    #[Permission('system.menu.update')]
    #[OA\Post(
        path: '/system/menu/batch-sort',
        summary: '批量排序菜单',
        security: [['bearerAuth' => []]],
        tags: ['菜单管理'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['items'],
            properties: [
                new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'parent_id', type: 'integer'),
                    new OA\Property(property: 'sort', type: 'integer'),
                ], type: 'object'), description: '排序数据'),
            ]
        )),
        responses: [new OA\Response(response: 200, description: '排序成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function batchSort(): Response
    {
        $items = $this->request->post('items', []);

        if (empty($items) || !is_array($items)) {
            return $this->error(lang('business.sort_data_required'));
        }

        try {
            $this->menuService->batchSort($items);
            return $this->success(lang('messages.sort_success'));
        } catch (\Throwable $e) {
            $this->log(lang('business.menu_sort_failed_log'), ['error' => $e->getMessage()]);
            return $this->error($e->getMessage() ?: lang('messages.sort_failed'));
        }
    }
}
