<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use app\service\system\RoleService;
use app\service\system\MenuService;
use app\adminapi\validate\v1\system\RoleValidate;
use core\base\Controller;
use think\Response;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '角色管理', description: '角色的增删改查、权限分配')]
class RoleController extends Controller
{
    protected RoleService $roleService;
    protected MenuService $menuService;

    #[Permission('system.role.list')]
    #[OA\Get(
        path: '/system/role',
        summary: '获取角色列表',
        security: [['bearerAuth' => []]],
        tags: ['角色管理'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'keyword', in: 'query', description: '关键词搜索', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function index(): Response
    {
        $params = $this->getRequestData();
        $result = $this->roleService->getRoleList($params);

        return $this->paginate($result);
    }

    #[Permission('system.role.list')]
    #[OA\Get(
        path: '/system/role/{id}',
        summary: '获取角色详情',
        security: [['bearerAuth' => []]],
        tags: ['角色管理'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function show(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->roleService->getRolePermissions($id);

        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('system.role.create')]
    #[OA\Post(
        path: '/system/role',
        summary: '创建角色',
        security: [['bearerAuth' => []]],
        tags: ['角色管理'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'title'],
            properties: [
                new OA\Property(property: 'name', type: 'string', description: '角色标识'),
                new OA\Property(property: 'title', type: 'string', description: '角色名称'),
                new OA\Property(property: 'description', type: 'string', description: '描述'),
                new OA\Property(property: 'sort', type: 'integer', description: '排序'),
                new OA\Property(property: 'status', type: 'integer', enum: [0, 1]),
            ]
        )),
        responses: [new OA\Response(response: 200, description: '创建成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function store(): Response
    {
        $data = $this->request->post();
        $this->validate($data, RoleValidate::class, [], 'create');

        $data['created_by'] = $this->getUserId();
        $result = $this->roleService->createRole($data);

        return $this->success(lang('messages.create_success'), $result);
    }

    #[Permission('system.role.update')]
    #[OA\Put(
        path: '/system/role/{id}',
        summary: '更新角色',
        security: [['bearerAuth' => []]],
        tags: ['角色管理'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'title', type: 'string'),
            new OA\Property(property: 'description', type: 'string'),
            new OA\Property(property: 'sort', type: 'integer'),
            new OA\Property(property: 'status', type: 'integer', enum: [0, 1]),
        ])),
        responses: [new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function update(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->post();
        $this->validate($data, RoleValidate::class, [], 'update');

        $data['updated_by'] = $this->getUserId();
        $result = $this->roleService->updateRole($id, $data);

        return $this->success(lang('messages.update_success'));
    }

    #[Permission('system.role.delete')]
    #[OA\Delete(
        path: '/system/role/{id}',
        summary: '删除角色',
        security: [['bearerAuth' => []]],
        tags: ['角色管理'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->roleService->deleteRole($id);

        return $this->success(lang('messages.delete_success'));
    }

    #[Permission('system.role.permission')]
    #[OA\Put(
        path: '/system/role/{id}/assign-permissions',
        summary: '角色授权（分配权限和菜单）',
        security: [['bearerAuth' => []]],
        tags: ['角色管理'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'permission_ids', type: 'array', items: new OA\Items(type: 'integer'), description: '权限ID列表'),
            new OA\Property(property: 'menu_ids', type: 'array', items: new OA\Items(type: 'integer'), description: '菜单ID列表'),
        ])),
        responses: [new OA\Response(response: 200, description: '授权成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function assignPermissions(): Response
    {
        $id = (int) $this->request->param('id');
        $menuIds = $this->request->post('menu_ids', []);

        $this->roleService->assignPermissions($id, $menuIds);

        return $this->success(lang('messages.authorize_success'));
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/system/role/permission/tree',
        summary: '获取权限树',
        security: [['bearerAuth' => []]],
        tags: ['角色管理'],
        responses: [new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function permissionTree(): Response
    {
        // 权限已合并到菜单树，返回菜单树即可
        $result = $this->menuService->getMenuTree();
        return $this->success(lang('messages.get_success'), $result);
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/system/role/menu/tree',
        summary: '获取菜单树',
        security: [['bearerAuth' => []]],
        tags: ['角色管理'],
        responses: [new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function menuTree(): Response
    {
        $result = $this->menuService->getMenuTree();
        return $this->success(lang('messages.get_success'), $result);
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/system/role/options',
        summary: '获取所有角色选项',
        security: [['bearerAuth' => []]],
        tags: ['角色管理'],
        responses: [new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function options(): Response
    {
        $result = $this->roleService->getAllRoleOptions();
        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('system.role.delete')]
    #[OA\Post(
        path: '/system/role/batch-delete',
        summary: '批量删除角色',
        security: [['bearerAuth' => []]],
        tags: ['角色管理'],
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
            return $this->error(lang('business.please_select_role'));
        }

        foreach ($ids as $id) {
            $this->roleService->deleteRole($id);
        }

        return $this->success(lang('messages.batch_delete_success'));
    }

    #[Permission('system.role.list')]
    #[OA\Get(
        path: '/system/role/{id}/permissions',
        summary: '获取角色权限',
        security: [['bearerAuth' => []]],
        tags: ['角色管理'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function permissions(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->roleService->getRolePermissions($id);
        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('system.role.status')]
    #[OA\Put(
        path: '/system/role/{id}/status',
        summary: '更新角色状态',
        security: [['bearerAuth' => []]],
        tags: ['角色管理'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'status', type: 'integer', enum: [0, 1]),
        ])),
        responses: [new OA\Response(response: 200, description: '状态更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function status(): Response
    {
        $id = (int) $this->request->param('id');
        $status = (int) $this->request->post('status');

        $this->roleService->updateStatus($id, $status);

        return $this->success(lang('messages.status_update_success'));
    }
}
