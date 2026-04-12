<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use app\service\system\AdminService;
use app\service\system\RoleService;
use app\adminapi\validate\v1\system\AdminValidate;
use core\base\Controller;
use think\Response;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '管理员管理', description: '管理员的增删改查、状态、密码管理')]
class AdminController extends Controller
{
    protected AdminService $adminService;
    protected RoleService $roleService;

    #[Permission('system.admin.list')]
    #[OA\Get(
        path: '/system/admin',
        summary: '获取管理员列表',
        security: [['bearerAuth' => []]],
        tags: ['管理员管理'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'keyword', in: 'query', description: '关键词搜索', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', description: '状态(0禁用 1启用)', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function index(): Response
    {
        $params = $this->getRequestData();
        $result = $this->adminService->getAdminList($params);

        return $this->paginate($result);
    }

    #[Permission('system.admin.list')]
    #[OA\Get(
        path: '/system/admin/{id}',
        summary: '获取管理员详情',
        security: [['bearerAuth' => []]],
        tags: ['管理员管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '管理员ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function show(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->adminService->getAdminInfo($id);

        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('system.admin.create')]
    #[OA\Post(
        path: '/system/admin',
        summary: '创建管理员',
        security: [['bearerAuth' => []]],
        tags: ['管理员管理'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', description: '用户名'),
                    new OA\Property(property: 'password', type: 'string', description: '密码'),
                    new OA\Property(property: 'nickname', type: 'string', description: '昵称'),
                    new OA\Property(property: 'email', type: 'string', description: '邮箱'),
                    new OA\Property(property: 'mobile', type: 'string', description: '手机号'),
                    new OA\Property(property: 'avatar', type: 'string', description: '头像URL'),
                    new OA\Property(property: 'department', type: 'string', description: '部门'),
                    new OA\Property(property: 'position', type: 'string', description: '职位'),
                    new OA\Property(property: 'role_ids', type: 'array', items: new OA\Items(type: 'integer'), description: '角色ID列表'),
                    new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '创建成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '验证失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function store(): Response
    {
        $data = $this->request->post();
        $this->validate($data, AdminValidate::class, [], 'create');

        $data['created_by'] = $this->getUserId();
        $result = $this->adminService->createAdmin($data);

        return $this->success(lang('messages.create_success'), $result);
    }

    #[Permission('system.admin.update')]
    #[OA\Put(
        path: '/system/admin/{id}',
        summary: '更新管理员',
        security: [['bearerAuth' => []]],
        tags: ['管理员管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'nickname', type: 'string'),
                new OA\Property(property: 'email', type: 'string'),
                new OA\Property(property: 'mobile', type: 'string'),
                new OA\Property(property: 'avatar', type: 'string'),
                new OA\Property(property: 'department', type: 'string'),
                new OA\Property(property: 'position', type: 'string'),
                new OA\Property(property: 'role_ids', type: 'array', items: new OA\Items(type: 'integer')),
                new OA\Property(property: 'status', type: 'integer', enum: [0, 1]),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function update(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->post();
        $this->validate($data, AdminValidate::class, [], 'update');

        $data['updated_by'] = $this->getUserId();
        $result = $this->adminService->updateAdmin($id, $data);

        return $this->success(lang('messages.update_success'));
    }

    #[Permission('system.admin.delete')]
    #[OA\Delete(
        path: '/system/admin/{id}',
        summary: '删除管理员',
        security: [['bearerAuth' => []]],
        tags: ['管理员管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->adminService->deleteAdmin($id, $this->getUserId());

        return $this->success(lang('messages.delete_success'));
    }

    #[Permission('system.admin.delete')]
    #[OA\Post(
        path: '/system/admin/batch-delete',
        summary: '批量删除管理员',
        security: [['bearerAuth' => []]],
        tags: ['管理员管理'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids'],
                properties: [
                    new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'), description: '管理员ID列表'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '批量删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function batchDelete(): Response
    {
        $ids = $this->request->post('ids', []);

        if (empty($ids)) {
            return $this->error(lang('business.please_select_admin'));
        }

        $this->adminService->batchDeleteAdmin($ids, $this->getUserId());

        return $this->success(lang('messages.batch_delete_success'));
    }

    #[Permission('system.admin.status')]
    #[OA\Put(
        path: '/system/admin/{id}/status',
        summary: '更新管理员状态',
        security: [['bearerAuth' => []]],
        tags: ['管理员管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '状态更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function status(): Response
    {
        $id = (int) $this->request->param('id');
        $status = (int) $this->request->post('status');

        $this->adminService->updateStatus($id, $status, $this->getUserId());

        return $this->success(lang('messages.status_update_success'));
    }

    #[Permission('system.admin.update')]
    #[OA\Put(
        path: '/system/admin/{id}/reset-password',
        summary: '重置管理员密码',
        security: [['bearerAuth' => []]],
        tags: ['管理员管理'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [
                    new OA\Property(property: 'password', type: 'string', description: '新密码(6-20位)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '密码重置成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function resetPassword(): Response
    {
        $id = (int) $this->request->param('id');
        $password = $this->request->post('password');

        $this->validate(['password' => $password], [
            'password' => 'require|length:6,20'
        ]);

        $this->adminService->resetPassword($id, $password);

        return $this->success(lang('messages.password_reset_success'));
    }

    #[PermissionSkip]
    #[OA\Put(
        path: '/system/admin/change-password',
        summary: '修改当前用户密码',
        security: [['bearerAuth' => []]],
        tags: ['管理员管理'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['old_password', 'new_password'],
                properties: [
                    new OA\Property(property: 'old_password', type: 'string', description: '旧密码'),
                    new OA\Property(property: 'new_password', type: 'string', description: '新密码(6-20位)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '密码修改成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function changePassword(): Response
    {
        $data = $this->request->post();
        $this->validate($data, [
            'old_password' => 'require',
            'new_password' => 'require|length:6,20|different:old_password'
        ]);

        $this->adminService->changePassword(
            $this->getUserId(),
            $data['old_password'],
            $data['new_password']
        );

        return $this->success(lang('messages.password_change_success'));
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/system/admin/role/options',
        summary: '获取角色选项列表',
        security: [['bearerAuth' => []]],
        tags: ['管理员管理'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function roleOptions(): Response
    {
        $result = $this->roleService->getAllRoleOptions();
        return $this->success(lang('messages.get_success'), $result);
    }
}
