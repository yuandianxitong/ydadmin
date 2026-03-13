<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use core\base\Controller;
use app\service\system\DepartmentService;
use app\adminapi\validate\v1\system\DepartmentValidate;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '部门管理', description: '部门树形结构管理')]
class DepartmentController extends Controller
{
    protected DepartmentService $service;

    #[Permission('system.department.list')]
    #[OA\Get(
        path: '/system/department',
        summary: '部门树形列表',
        security: [['bearerAuth' => []]],
        tags: ['部门管理'],
        parameters: [
            new OA\Parameter(name: 'keyword', in: 'query', description: '关键词搜索', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', description: '状态(0禁用 1启用)', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function index()
    {
        $params = $this->getRequestData([
            'keyword' => '',
            'status'  => '',
        ]);

        $result = $this->service->getDepartmentTree($params);
        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('system.department.list')]
    #[OA\Get(
        path: '/system/department/{id}',
        summary: '部门详情',
        security: [['bearerAuth' => []]],
        tags: ['部门管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '部门ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '部门不存在', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function show()
    {
        $id = (int) $this->request->param('id');
        $result = $this->service->getDepartmentDetail($id);

        if (!$result) {
            return $this->error(lang('business.dept_not_found'));
        }

        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('system.department.create')]
    #[OA\Post(
        path: '/system/department',
        summary: '创建部门',
        security: [['bearerAuth' => []]],
        tags: ['部门管理'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', description: '部门名称'),
                    new OA\Property(property: 'parent_id', type: 'integer', description: '父级部门ID'),
                    new OA\Property(property: 'leader', type: 'string', description: '负责人'),
                    new OA\Property(property: 'phone', type: 'string', description: '联系电话'),
                    new OA\Property(property: 'email', type: 'string', description: '邮箱'),
                    new OA\Property(property: 'sort', type: 'integer', description: '排序'),
                    new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '创建成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '验证失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function store()
    {
        $data = $this->request->post();
        $this->validate($data, DepartmentValidate::class, [], 'create');

        $data['created_by'] = $this->getUserId();
        $result = $this->service->createDepartment($data);

        return $this->success(lang('messages.create_success'), $result);
    }

    #[Permission('system.department.update')]
    #[OA\Put(
        path: '/system/department/{id}',
        summary: '更新部门',
        security: [['bearerAuth' => []]],
        tags: ['部门管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '部门ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'name', type: 'string', description: '部门名称'),
                new OA\Property(property: 'parent_id', type: 'integer', description: '父级部门ID'),
                new OA\Property(property: 'leader', type: 'string', description: '负责人'),
                new OA\Property(property: 'phone', type: 'string', description: '联系电话'),
                new OA\Property(property: 'email', type: 'string', description: '邮箱'),
                new OA\Property(property: 'sort', type: 'integer', description: '排序'),
                new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function update()
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->post();
        $this->validate($data, DepartmentValidate::class, [], 'update');

        $data['updated_by'] = $this->getUserId();
        $this->service->updateDepartment($id, $data);

        return $this->success(lang('messages.update_success'));
    }

    #[Permission('system.department.delete')]
    #[OA\Delete(
        path: '/system/department/{id}',
        summary: '删除部门',
        security: [['bearerAuth' => []]],
        tags: ['部门管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '部门ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function delete()
    {
        $id = (int) $this->request->param('id');
        $this->service->deleteDepartment($id);
        return $this->success(lang('messages.delete_success'));
    }

    #[Permission('system.department.update')]
    #[OA\Put(
        path: '/system/department/{id}/status',
        summary: '更新部门状态',
        security: [['bearerAuth' => []]],
        tags: ['部门管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '部门ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function status()
    {
        $id = (int) $this->request->param('id');
        $status = (int) $this->request->post('status');
        $this->service->updateStatus($id, $status);
        return $this->success(lang('messages.update_success'));
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/system/department/options',
        summary: '部门选项（下拉树形）',
        security: [['bearerAuth' => []]],
        tags: ['部门管理'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function options()
    {
        $result = $this->service->getDepartmentOptions();
        return $this->success(lang('messages.get_success'), $result);
    }
}
