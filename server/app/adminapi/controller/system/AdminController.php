<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\AuthenticatedController;
use app\service\system\AdminService;
use app\service\system\RoleService;
use core\context\RequestContext;
use core\permission\Permission;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/** 管理员（契约 §2.2）。唯一性交给 Service（排除自身、含软删行），这里只做格式校验。 */
#[RouteGroup('/adminapi/system/admin')]
class AdminController extends AuthenticatedController
{
    #[Inject]
    protected AdminService $adminService;

    #[Inject]
    protected RoleService $roleService;

    #[Get('')]
    #[Permission('system.admin.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->adminService->getAdminList((array) $request->get(), $page, $limit));
    }

    #[Get('/{id:\d+}')]
    #[Permission('system.admin.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->adminService->getAdminInfo((int) $id), lang('messages.get_success'));
    }

    #[Post('')]
    #[Permission('system.admin.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

        return $this->success($this->adminService->createAdmin($data), lang('messages.create_success'));
    }

    #[Put('/{id:\d+}')]
    #[Permission('system.admin.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());
        $this->adminService->updateAdmin((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Delete('/{id:\d+}')]
    #[Permission('system.admin.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->adminService->deleteAdmin((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Post('/batch-delete')]
    #[Permission('system.admin.delete')]
    public function batchDelete(Request $request): Response
    {
        $ids = $this->body($request)['ids'] ?? [];
        if (!is_array($ids) || $ids === []) {
            return $this->error(lang('business.please_select_admin'));
        }
        $count = $this->adminService->batchDeleteAdmins(array_values(array_map('intval', array_filter($ids, 'is_numeric'))));

        return $this->success(['count' => $count], lang('messages.batch_delete_success'));
    }

    #[Put('/{id:\d+}/status')]
    #[Permission('system.admin.status')]
    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->statusRules(), [
            'status.required' => 'validation.status_invalid',
            'status.integer'  => 'validation.status_invalid',
            'status.in'       => 'validation.status_invalid',
        ]);
        $this->adminService->updateStatus((int) $id, (int) $data['status']);

        return $this->success([], lang('messages.status_update_success'));
    }

    #[Put('/{id:\d+}/reset-password')]
    #[Permission('system.admin.update')]
    public function resetPassword(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->resetPasswordRules(), [
            'password.required' => 'validation.password_require',
            'password.max'      => 'validation.password_max',
        ]);
        $this->adminService->resetPassword((int) $id, (string) $data['password']);

        return $this->success([], lang('messages.password_reset_success'));
    }

    #[Put('/change-password')]
    #[PermissionSkip]
    public function changePassword(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->changePasswordRules(), [
            'old_password.required'  => 'validation.password_require',
            'new_password.required'  => 'validation.password_require',
            'new_password.max'       => 'validation.password_max',
            'new_password.different' => 'validation.new_password_different',
        ]);
        $this->adminService->changePassword(RequestContext::actingUser(), (string) $data['old_password'], (string) $data['new_password']);

        return $this->success([], lang('messages.password_change_success'));
    }

    #[Get('/role/options')]
    #[PermissionSkip]
    public function roleOptions(): Response
    {
        return $this->success($this->roleService->getAllRoleOptions(), lang('messages.get_success'));
    }

    /** @return array<string, string> */
    private function rules(string $scene): array
    {
        $isCreate = $scene === 'create';
        // username/email 在更新场景下用 sometimes|required：字段不传时跳过校验（局部更新），
        // 传了空字符串则必须校验失败——不能像 nullable 那样对 '' 直接放行（会写出空用户名/邮箱、非法状态）。
        // status 两个场景都用 sometimes|required：新建时不传取默认 1，传空串不能被当成 0（禁用）保存。
        // role_ids 用 sometimes|array：不传不改、[] 清空，传 null 拒绝（否则等同清空全部角色）。
        $presence = $isCreate ? 'required' : 'sometimes|required';

        return [
            'username'      => "{$presence}|string|min:3|max:20|alpha_dash:ascii",
            'email'         => "{$presence}|email|max:100",
            'password'      => ($isCreate ? 'required' : 'nullable') . '|' . $this->adminService->passwordRule(),
            'mobile'        => 'nullable|regex:/^1[3-9]\d{9}$/',
            'nickname'      => 'nullable|string|min:2|max:20',
            'avatar'        => 'nullable|string|max:255',
            'department_id' => 'nullable|integer|min:0',
            'position'      => 'nullable|string|max:100',
            'status'        => 'sometimes|required|integer|in:0,1',
            'role_ids'      => 'sometimes|array',
            'role_ids.*'    => 'integer|min:1',
        ];
    }

    /**
     * 薄包装，委派给既有的 rules('create')。
     *
     * @return array<string, string>
     */
    private function storeRules(): array
    {
        return $this->rules('create');
    }

    /**
     * 薄包装，委派给既有的 rules('update')。
     *
     * @return array<string, string>
     */
    private function updateRules(): array
    {
        return $this->rules('update');
    }

    /** @return array<string, string> */
    private function statusRules(): array
    {
        return ['status' => 'required|integer|in:0,1'];
    }

    /**
     * 容器解析出的控制器实例是完整初始化过的，$this->adminService 已注入，实调即得真值——
     * 这正是 RuleReflector 必须走 DI 容器而不能 newInstanceWithoutConstructor() 的原因（spec §5 约束 1）。
     *
     * @return array<string, string>
     */
    private function resetPasswordRules(): array
    {
        return ['password' => 'required|' . $this->adminService->passwordRule()];
    }

    /** @return array<string, string> */
    private function changePasswordRules(): array
    {
        return [
            'old_password' => 'required|string',
            'new_password' => 'required|' . $this->adminService->passwordRule() . '|different:old_password',
        ];
    }

    /** @return array<string, string> 值即 lang key；password.min 用规则默认文案（最短长度随配置变化） */
    private function messages(): array
    {
        return [
            'username.required'   => 'validation.username_require',
            'username.min'        => 'validation.username_length_3_20',
            'username.max'        => 'validation.username_length_3_20',
            'username.alpha_dash' => 'validation.username_alpha_dash',
            'email.required'      => 'validation.email_require',
            'email.email'         => 'validation.email_format',
            'password.required'   => 'validation.password_require',
            'password.max'        => 'validation.password_max',
            'mobile.regex'        => 'validation.mobile_format',
            'nickname.min'        => 'validation.nickname_length',
            'nickname.max'        => 'validation.nickname_length',
            'status.required'     => 'validation.status_invalid',
            'status.in'           => 'validation.status_invalid',
            'role_ids.array'      => 'validation.role_ids_array',
            'role_ids.*.integer'  => 'validation.role_ids_integer',
        ];
    }
}
