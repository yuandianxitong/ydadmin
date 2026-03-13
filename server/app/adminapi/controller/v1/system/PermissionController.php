<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use app\service\system\PermissionService;
use app\adminapi\validate\v1\system\PermissionValidate;
use core\base\Controller;
use think\Response;
use core\attribute\Permission as Perm;
use core\attribute\PermissionSkip;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '权限管理', description: '权限的增删改查、权限树')]
class PermissionController extends Controller
{
    protected PermissionService $permissionService;

    #[Perm('system.permission.list')]
    #[OA\Get(
        path: '/system/permission',
        summary: '获取权限列表',
        security: [['bearerAuth' => []]],
        tags: ['权限管理'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'grouped', in: 'query', description: '是否按分组返回', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))]
    )]
    public function index(): Response
    {
        $params = $this->getRequestData();
        $result = $this->permissionService->getPermissionList($params);

        if (!empty($params['grouped'])) {
            return $this->success(lang('messages.get_success'), $result);
        }

        return $this->paginate($result);
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/system/permission/tree',
        summary: '获取权限树',
        security: [['bearerAuth' => []]],
        tags: ['权限管理'],
        responses: [new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function tree(): Response
    {
        $result = $this->permissionService->getPermissionTree();
        return $this->success(lang('messages.get_success'), $result);
    }

    #[Perm('system.permission.create')]
    #[OA\Post(
        path: '/system/permission',
        summary: '创建权限',
        security: [['bearerAuth' => []]],
        tags: ['权限管理'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'title'],
            properties: [
                new OA\Property(property: 'name', type: 'string', description: '权限标识(如system.admin.create)'),
                new OA\Property(property: 'title', type: 'string', description: '权限名称'),
                new OA\Property(property: 'group', type: 'string', description: '权限分组'),
                new OA\Property(property: 'description', type: 'string', description: '描述'),
                new OA\Property(property: 'sort', type: 'integer'),
                new OA\Property(property: 'status', type: 'integer', enum: [0, 1]),
            ]
        )),
        responses: [new OA\Response(response: 200, description: '创建成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function store(): Response
    {
        $data = $this->request->post();
        $this->validate($data, PermissionValidate::class, [], 'create');

        $result = $this->permissionService->createPermission($data);

        return $this->success(lang('messages.create_success'), $result);
    }

    #[Perm('system.permission.update')]
    #[OA\Put(
        path: '/system/permission/{id}',
        summary: '更新权限',
        security: [['bearerAuth' => []]],
        tags: ['权限管理'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'title', type: 'string'),
            new OA\Property(property: 'group', type: 'string'),
            new OA\Property(property: 'description', type: 'string'),
        ])),
        responses: [new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function update(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->post();
        $this->validate($data, PermissionValidate::class, [], 'update');

        $this->permissionService->updatePermission($id, $data);

        return $this->success(lang('messages.update_success'));
    }

    #[Perm('system.permission.delete')]
    #[OA\Delete(
        path: '/system/permission/{id}',
        summary: '删除权限',
        security: [['bearerAuth' => []]],
        tags: ['权限管理'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->permissionService->deletePermission($id);

        return $this->success(lang('messages.delete_success'));
    }

    #[Perm('system.permission.create')]
    #[OA\Post(
        path: '/system/permission/batch-create',
        summary: '批量创建权限',
        security: [['bearerAuth' => []]],
        tags: ['权限管理'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['permissions'],
            properties: [
                new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'group', type: 'string'),
                ], type: 'object')),
            ]
        )),
        responses: [new OA\Response(response: 200, description: '批量创建成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))]
    )]
    public function batchCreate(): Response
    {
        $permissions = $this->request->post('permissions', []);

        if (empty($permissions)) {
            return $this->error(lang('business.permission_data_required'));
        }

        $this->permissionService->batchCreatePermissions($permissions);

        return $this->success(lang('messages.batch_create_success'));
    }
}
