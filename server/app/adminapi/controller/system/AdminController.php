<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\service\system\AdminService;
use app\service\system\RoleService;
use core\base\Controller;
use core\context\RequestContext;
use core\permission\Permission;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/** 管理员（契约 §2.2）。唯一性交给 Service（排除自身、含软删行），这里只做格式校验。 */
class AdminController extends Controller
{
    #[Inject]
    protected AdminService $adminService;

    #[Inject]
    protected RoleService $roleService;

    #[Permission('system.admin.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->adminService->getAdminList((array) $request->get(), $page, $limit));
    }

    #[Permission('system.admin.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->adminService->getAdminInfo((int) $id), lang('messages.get_success'));
    }

    #[Permission('system.admin.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->rules('create'), $this->messages());

        return $this->success($this->adminService->createAdmin($data), lang('messages.create_success'));
    }

    #[Permission('system.admin.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->rules('update'), $this->messages());
        $this->adminService->updateAdmin((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Permission('system.admin.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->adminService->deleteAdmin((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

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

    #[Permission('system.admin.status')]
    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), ['status' => 'required|integer|in:0,1'], [
            'status.required' => 'validation.status_invalid',
            'status.integer'  => 'validation.status_invalid',
            'status.in'       => 'validation.status_invalid',
        ]);
        $this->adminService->updateStatus((int) $id, (int) $data['status']);

        return $this->success([], lang('messages.status_update_success'));
    }

    #[Permission('system.admin.update')]
    public function resetPassword(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), ['password' => 'required|' . $this->adminService->passwordRule()], [
            'password.required' => 'validation.password_require',
            'password.max'      => 'validation.password_max',
        ]);
        $this->adminService->resetPassword((int) $id, (string) $data['password']);

        return $this->success([], lang('messages.password_reset_success'));
    }

    #[PermissionSkip]
    public function changePassword(Request $request): Response
    {
        $data = $this->validate($this->body($request), [
            'old_password' => 'required|string',
            'new_password' => 'required|' . $this->adminService->passwordRule() . '|different:old_password',
        ], [
            'old_password.required'  => 'validation.password_require',
            'new_password.required'  => 'validation.password_require',
            'new_password.max'       => 'validation.password_max',
            'new_password.different' => 'validation.new_password_different',
        ]);
        $this->adminService->changePassword(RequestContext::actingUser(), (string) $data['old_password'], (string) $data['new_password']);

        return $this->success([], lang('messages.password_change_success'));
    }

    #[PermissionSkip]
    public function roleOptions(): Response
    {
        return $this->success($this->roleService->getAllRoleOptions(), lang('messages.get_success'));
    }

    /** @return array<string, string> */
    private function rules(string $scene): array
    {
        $presence = $scene === 'create' ? 'required' : 'nullable';

        return [
            'username'      => "{$presence}|string|min:3|max:20|alpha_dash:ascii",
            'email'         => "{$presence}|email|max:100",
            'password'      => "{$presence}|" . $this->adminService->passwordRule(),
            'mobile'        => 'nullable|regex:/^1[3-9]\d{9}$/',
            'nickname'      => 'nullable|string|min:2|max:20',
            'avatar'        => 'nullable|string|max:255',
            'department_id' => 'nullable|integer|min:0',
            'position'      => 'nullable|string|max:100',
            'status'        => 'nullable|integer|in:0,1',
            'role_ids'      => 'nullable|array',
            'role_ids.*'    => 'integer|min:1',
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
            'status.in'           => 'validation.status_invalid',
            'role_ids.array'      => 'validation.role_ids_array',
            'role_ids.*.integer'  => 'validation.role_ids_integer',
        ];
    }
}
