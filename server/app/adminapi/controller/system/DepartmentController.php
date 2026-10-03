<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\AuthenticatedController;
use app\service\system\DepartmentService;
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

/**
 * 部门（契约 §2.5）。
 *
 * 端点：
 *   GET    /adminapi/system/department              index    system.department.list
 *   GET    /adminapi/system/department/options       options  PermissionSkip
 *   PUT    /adminapi/system/department/{id}/status   status   system.department.update
 *   GET    /adminapi/system/department/{id}          show     system.department.list
 *   POST   /adminapi/system/department                store    system.department.create
 *   PUT    /adminapi/system/department/{id}           update   system.department.update
 *   DELETE /adminapi/system/department/{id}           delete   system.department.delete
 *
 * update 场景的 parent_id/status/sort 用 sometimes|required：字段不传时跳过校验（局部更新），
 * 传了空字符串则必须校验失败——不能像 nullable 那样对 '' 直接放行（会写出非法 parent_id/status/sort，
 * 触发 MySQL 严格模式 500，见 Task 12 复盘）。
 */
#[RouteGroup('/adminapi/system/department')]
class DepartmentController extends AuthenticatedController
{
    #[Inject]
    protected DepartmentService $departmentService;

    #[Get('')]
    #[Permission('system.department.list')]
    public function index(Request $request): Response
    {
        $result = $this->departmentService->getDepartmentTree((array) $request->get());

        return $this->success($result, lang('messages.get_success'));
    }

    #[Get('/{id:\d+}')]
    #[Permission('system.department.list')]
    public function show(Request $request, string $id): Response
    {
        $result = $this->departmentService->getDepartmentDetail((int) $id);

        return $this->success($result, lang('messages.get_success'));
    }

    #[Get('/options')]
    #[PermissionSkip]
    public function options(): Response
    {
        $result = $this->departmentService->getDepartmentOptions();

        return $this->success($result, lang('messages.get_success'));
    }

    #[Post('')]
    #[Permission('system.department.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

        $result = $this->departmentService->createDepartment($data);

        return $this->success($result, lang('messages.create_success'));
    }

    #[Put('/{id:\d+}')]
    #[Permission('system.department.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());

        $this->departmentService->updateDepartment((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Put('/{id:\d+}/status')]
    #[Permission('system.department.update')]
    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->statusRules(), [
            'status.required' => 'validation.status_invalid',
            'status.integer'  => 'validation.status_invalid',
            'status.in'       => 'validation.status_invalid',
        ]);

        $this->departmentService->updateStatus((int) $id, (int) $data['status']);

        return $this->success([], lang('messages.status_update_success'));
    }

    #[Delete('/{id:\d+}')]
    #[Permission('system.department.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->departmentService->deleteDepartment((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    /**
     * parent_id：create 恒 required，update 用 sometimes|required（局部更新，见类注释）。
     * status/sort 两场景统一 sometimes|required：字段缺失时用 Service 默认值，传了空字符串必须失败。
     *
     * @return array<string, string>
     */
    private function rules(string $scene): array
    {
        return [
            'parent_id' => $scene === 'create' ? 'required|integer|min:0' : 'sometimes|required|integer|min:0',
            'name'      => 'required|string|max:100',
            'code'      => 'nullable|string|max:50',
            'leader'    => 'nullable|string|max:50',
            'phone'     => 'nullable|string|max:20',
            'email'     => 'nullable|email|max:100',
            'status'    => 'sometimes|required|integer|in:0,1',
            'sort'      => 'sometimes|required|integer|min:0',
            'remark'    => 'nullable|string|max:255',
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
     * message 值即 lang key（与 ValidatorFactory::resolveMessage 的约定一致，参考 §7）。
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'parent_id.required' => 'validation.parent_id_require',
            'parent_id.integer'  => 'validation.parent_id_integer',
            'parent_id.min'      => 'validation.parent_id_min',
            'name.required'      => 'validation.dept_name_require',
            'name.max'           => 'validation.dept_name_max',
            'code.max'           => 'validation.dept_code_max',
            'email.email'        => 'validation.email_format',
            'status.required'    => 'validation.status_invalid',
            'status.integer'     => 'validation.status_integer',
            'status.in'          => 'validation.status_invalid',
            'sort.required'      => 'validation.sort_integer',
            'sort.integer'       => 'validation.sort_integer',
            'sort.min'           => 'validation.sort_min',
            'remark.max'         => 'validation.remark_max',
        ];
    }
}
