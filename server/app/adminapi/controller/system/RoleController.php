<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\AuthenticatedController;
use app\service\system\MenuService;
use app\service\system\RoleService;
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
 * 角色（契约 §2.3）。
 *
 * 端点：
 *   GET    /adminapi/system/role                       index             system.role.list
 *   GET    /adminapi/system/role/permission/tree        permissionTree    PermissionSkip
 *   GET    /adminapi/system/role/menu/tree              menuTree          PermissionSkip
 *   GET    /adminapi/system/role/options                options           PermissionSkip
 *   POST   /adminapi/system/role/batch-delete           batchDelete       system.role.delete
 *   GET    /adminapi/system/role/{id}/permissions       permissions       system.role.list
 *   PUT    /adminapi/system/role/{id}/assign-permissions assignPermissions system.role.permission
 *   PUT    /adminapi/system/role/{id}/status            status            system.role.status
 *   GET    /adminapi/system/role/{id}                   show              system.role.list
 *   POST   /adminapi/system/role                        store             system.role.create
 *   PUT    /adminapi/system/role/{id}                   update            system.role.update
 *   DELETE /adminapi/system/role/{id}                   delete            system.role.delete
 *
 * update 场景刻意不含 menu_ids/permission_ids：改角色菜单权限走专门的 assign-permissions 端点，
 * 不和普通更新混在一起。
 */
#[RouteGroup('/adminapi/system/role')]
class RoleController extends AuthenticatedController
{
    #[Inject]
    protected RoleService $roleService;

    #[Inject]
    protected MenuService $menuService;

    #[Get('')]
    #[Permission('system.role.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->roleService->getRoleList((array) $request->get(), $page, $limit));
    }

    #[Get('/{id:\d+}')]
    #[Permission('system.role.list')]
    public function show(Request $request, string $id): Response
    {
        $result = $this->roleService->getRolePermissions((int) $id);

        return $this->success($result, lang('messages.get_success'));
    }

    #[Post('')]
    #[Permission('system.role.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

        $result = $this->roleService->createRole($data);

        return $this->success($result, lang('messages.create_success'));
    }

    #[Put('/{id:\d+}')]
    #[Permission('system.role.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());

        $this->roleService->updateRole((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Delete('/{id:\d+}')]
    #[Permission('system.role.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->roleService->deleteRole((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Post('/batch-delete')]
    #[Permission('system.role.delete')]
    public function batchDelete(Request $request): Response
    {
        $ids = $this->body($request)['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            return $this->error(lang('business.please_select_role'));
        }

        $this->roleService->batchDeleteRoles($ids);

        return $this->success([], lang('messages.batch_delete_success'));
    }

    #[Put('/{id:\d+}/assign-permissions')]
    #[Permission('system.role.permission')]
    public function assignPermissions(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->assignPermissionsRules(), [
            'menu_ids.present'     => 'validation.menu_ids_array',
            'menu_ids.array'       => 'validation.menu_ids_array',
            'menu_ids.*.integer'   => 'validation.menu_ids_integer',
            'menu_ids.*.min'       => 'validation.menu_ids_integer',
        ]);

        $this->roleService->assignPermissions((int) $id, $data['menu_ids']);

        return $this->success([], lang('messages.authorize_success'));
    }

    #[Get('/{id:\d+}/permissions')]
    #[Permission('system.role.list')]
    public function permissions(Request $request, string $id): Response
    {
        $result = $this->roleService->getRolePermissions((int) $id);

        return $this->success($result, lang('messages.get_success'));
    }

    #[Put('/{id:\d+}/status')]
    #[Permission('system.role.status')]
    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->statusRules(), [
            'status.required' => 'validation.status_invalid',
            'status.integer'  => 'validation.status_invalid',
            'status.in'       => 'validation.status_invalid',
        ]);

        $this->roleService->updateStatus((int) $id, (int) $data['status']);

        return $this->success([], lang('messages.status_update_success'));
    }

    #[Get('/permission/tree')]
    #[PermissionSkip]
    public function permissionTree(): Response
    {
        // 权限已合并到菜单树，返回菜单树即可
        $result = $this->menuService->getMenuTree();

        return $this->success($result, lang('messages.get_success'));
    }

    #[Get('/menu/tree')]
    #[PermissionSkip]
    public function menuTree(): Response
    {
        $result = $this->menuService->getMenuTree();

        return $this->success($result, lang('messages.get_success'));
    }

    #[Get('/options')]
    #[PermissionSkip]
    public function options(): Response
    {
        $result = $this->roleService->getAllRoleOptions();

        return $this->success($result, lang('messages.get_success'));
    }

    /**
     * update 场景用 sometimes|required：字段不传时跳过校验（局部更新），传了空字符串则必须校验
     * 失败——不能像 nullable 那样对 '' 直接放行（会写出空标识/名称、非法状态，见 Task 12 复盘）。
     *
     * @return array<string, string>
     */
    private function rules(string $scene): array
    {
        if ($scene === 'create') {
            return [
                'name'         => 'required|string|min:2|max:50|alpha_dash:ascii',
                'title'        => 'required|string|min:2|max:100',
                'description'  => 'nullable|string|max:500',
                // data_scope/sort 与 name/title/status 同理：字段缺失时用 sometimes 跳过（Service 有默认值），
                // 传了空字符串必须校验失败，否则 '' 会绕过 nullable 直接写库触发 MySQL 严格模式报错或写出非法值 0。
                'data_scope'   => 'sometimes|required|integer|in:1,2,3,4,5',
                'dept_ids'     => 'nullable|array',
                'dept_ids.*'   => 'integer|min:1',
                'status'       => 'sometimes|required|integer|in:0,1',
                'sort'         => 'sometimes|required|integer|min:0',
                // update 不含 menu_ids：改授权走 assign-permissions（契约 §2.3）
                'menu_ids'     => 'nullable|array',
                'menu_ids.*'   => 'integer|min:1',
            ];
        }

        return [
            'name'        => 'sometimes|required|string|min:2|max:50|alpha_dash:ascii',
            'title'       => 'sometimes|required|string|min:2|max:100',
            'description' => 'nullable|string|max:500',
            'data_scope'  => 'sometimes|required|integer|in:1,2,3,4,5',
            'dept_ids'    => 'nullable|array',
            'dept_ids.*'  => 'integer|min:1',
            'status'      => 'sometimes|required|integer|in:0,1',
            'sort'        => 'sometimes|required|integer|min:0',
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
    private function assignPermissionsRules(): array
    {
        return [
            'menu_ids'   => 'present|array',
            'menu_ids.*' => 'integer|min:1',
        ];
    }

    /** @return array<string, string> */
    private function statusRules(): array
    {
        return ['status' => 'required|integer|in:0,1'];
    }

    /** message 值即 lang key（与 ValidatorFactory::resolveMessage 的约定一致）。
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.required'            => 'validation.role_name_require',
            'name.min'                 => 'validation.role_name_length',
            'name.max'                 => 'validation.role_name_length',
            'name.alpha_dash'          => 'validation.role_name_alpha_dash',
            'title.required'           => 'validation.role_title_require',
            'title.min'                => 'validation.role_title_length',
            'title.max'                => 'validation.role_title_length',
            'description.max'         => 'validation.role_desc_max',
            'data_scope.required'      => 'validation.data_scope_invalid',
            'data_scope.integer'       => 'validation.data_scope_invalid',
            'data_scope.in'            => 'validation.data_scope_invalid',
            'status.required'          => 'validation.status_invalid',
            'status.integer'           => 'validation.status_integer',
            'status.in'                => 'validation.status_invalid',
            'sort.required'            => 'validation.sort_integer',
            'sort.integer'             => 'validation.sort_integer',
            'sort.min'                 => 'validation.sort_min',
            'menu_ids.array'           => 'validation.menu_ids_array',
            'menu_ids.*.integer'       => 'validation.menu_ids_integer',
            'dept_ids.array'           => 'validation.dept_ids_array',
            'dept_ids.*.integer'       => 'validation.dept_ids_integer',
        ];
    }
}
