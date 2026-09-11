<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\service\system\AdminService;
use app\service\system\MenuService;
use core\base\Controller;
use core\context\RequestContext;
use core\permission\Permission;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

class MenuController extends Controller
{
    #[Inject]
    protected MenuService $menuService;

    #[Inject]
    protected AdminService $adminService;

    /** 契约 §2.4：默认返回全部（含禁用），only_enabled 为真时只返回启用的。 */
    #[Permission('system.menu.list')]
    public function index(Request $request): Response
    {
        // 契约 §2.4：默认返回全部（含禁用），only_enabled 为真时只返回启用的
        $onlyEnabled = filter_var($request->get('only_enabled', false), FILTER_VALIDATE_BOOLEAN);

        return $this->success($this->menuService->getMenuTree($onlyEnabled), lang('messages.get_success'));
    }

    /** 菜单选项树（表单选择用，含虚拟根节点「根目录」）。 */
    #[PermissionSkip]
    public function options(Request $request): Response
    {
        $excludeId = (int) $request->get('exclude_id', 0);

        return $this->success($this->menuService->getMenuOptions($excludeId), lang('messages.get_success'));
    }

    /** 当前管理员的前端路由树，与 auth/info.routes 同源（契约 §4.3）。 */
    #[PermissionSkip]
    public function routes(): Response
    {
        $admin = $this->adminService->getSelfInfo(RequestContext::actingUser());

        return $this->success($this->menuService->getFrontendRoutes((array) $admin['menu_ids']), lang('messages.get_success'));
    }

    #[Permission('system.menu.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->rules(), $this->messages());

        return $this->success($this->menuService->createMenu($data), lang('messages.create_success'));
    }

    #[Permission('system.menu.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->rules(), $this->messages());
        $this->menuService->updateMenu((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Permission('system.menu.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->menuService->deleteMenu((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Permission('system.menu.delete')]
    public function batchDelete(Request $request): Response
    {
        $ids = $this->body($request)['ids'] ?? [];

        if (!is_array($ids) || $ids === []) {
            return $this->error(lang('business.please_select_menu'));
        }

        $this->menuService->batchDeleteMenus($ids);

        return $this->success([], lang('messages.batch_delete_success'));
    }

    #[Permission('system.menu.update')]
    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), ['status' => 'required|integer|in:0,1'], [
            'status.required' => 'validation.status_invalid',
            'status.integer'  => 'validation.status_invalid',
            'status.in'       => 'validation.status_invalid',
        ]);
        $this->menuService->updateMenu((int) $id, ['status' => (int) $data['status']]);

        return $this->success([], lang('messages.update_success'));
    }

    #[Permission('system.menu.update')]
    public function batchSort(Request $request): Response
    {
        $items = $this->body($request)['items'] ?? [];

        if (!is_array($items) || $items === []) {
            return $this->error(lang('business.sort_data_required'));
        }

        // items 里的字符串数字（表单/JSON 混合提交常见）统一转 int，与 MenuService::batchSort
        // 的 is_int 强类型校验对齐——由 Controller 层负责类型归一化，Service 只做业务规则。
        $normalized = array_map(static function ($row) {
            return [
                'id'        => isset($row['id']) ? (int) $row['id'] : null,
                'parent_id' => isset($row['parent_id']) ? (int) $row['parent_id'] : null,
                'sort'      => isset($row['sort']) ? (int) $row['sort'] : null,
            ];
        }, $items);

        $this->menuService->batchSort($normalized);

        return $this->success([], lang('messages.sort_success'));
    }

    /**
     * 校验规则（同一套用于 store 与 update）。
     *
     * 整型/布尔字段用 sometimes|required 而非 nullable：Laravel 对非隐式规则会跳过空字符串
     * （nullable 对 '' 直接放行），若整型/布尔列仍用 nullable，空字符串会绕过校验直接写库，
     * MySQL 严格模式下产生 500。sometimes|required 能保证字段一旦出现就必须是合法值，
     * 字段整体缺失时仍走「不传即不更新」的局部更新语义。
     *
     * @return array<string, string>
     */
    private function rules(): array
    {
        return [
            'parent_id'     => 'sometimes|required|integer|min:0',
            'type'          => 'required|integer|in:1,2,3',
            'title'         => 'required|string|min:1|max:100',
            'name'          => 'nullable|string|max:100|required_if:type,2',
            'path'          => 'nullable|string|max:200|required_if:type,2',
            'component'     => 'nullable|string|max:255|required_if:type,2',
            'redirect'      => 'nullable|string|max:200',
            'icon'          => 'nullable|string|max:100',
            'permission'    => 'nullable|string|max:100|required_if:type,3',
            'active_menu'   => 'nullable|string|max:200',
            'is_hidden'     => 'sometimes|required|boolean',
            'is_cache'      => 'sometimes|required|boolean',
            'is_affix'      => 'sometimes|required|boolean',
            'is_iframe'     => 'sometimes|required|boolean',
            'breadcrumb'    => 'sometimes|required|boolean',
            'external_link' => 'nullable|url',
            'status'        => 'sometimes|required|integer|in:0,1',
            'sort'          => 'sometimes|required|integer|min:0',
            'meta'          => 'nullable|array',
        ];
    }

    /**
     * message 值即 lang key（与 ValidatorFactory::resolveMessage 的约定一致，参考 §7）。
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'parent_id.required'     => 'validation.parent_id_integer',
            'parent_id.integer'      => 'validation.parent_id_integer',
            'parent_id.min'          => 'validation.parent_id_min',
            'type.required'          => 'validation.menu_type_require',
            'type.integer'           => 'validation.menu_type_invalid',
            'type.in'                => 'validation.menu_type_invalid',
            'title.required'         => 'validation.menu_title_require',
            'title.min'              => 'validation.menu_title_length',
            'title.max'              => 'validation.menu_title_length',
            'name.max'               => 'validation.route_name_length',
            'name.required_if'       => 'validation.menu_name_require',
            'path.max'               => 'validation.route_path_length',
            'path.required_if'       => 'validation.menu_path_require',
            'component.max'          => 'validation.component_length',
            'component.required_if'  => 'validation.menu_component_require',
            'redirect.max'           => 'validation.redirect_length',
            'icon.max'               => 'validation.icon_length',
            'permission.max'         => 'validation.permission_length',
            'permission.required_if' => 'validation.button_permission_require',
            'active_menu.max'        => 'validation.active_menu_length',
            'is_hidden.required'     => 'validation.is_hidden_boolean',
            'is_hidden.boolean'      => 'validation.is_hidden_boolean',
            'is_cache.required'      => 'validation.is_cache_boolean',
            'is_cache.boolean'       => 'validation.is_cache_boolean',
            'is_affix.required'      => 'validation.is_affix_boolean',
            'is_affix.boolean'       => 'validation.is_affix_boolean',
            'is_iframe.required'     => 'validation.is_iframe_boolean',
            'is_iframe.boolean'      => 'validation.is_iframe_boolean',
            'breadcrumb.required'    => 'validation.breadcrumb_boolean',
            'breadcrumb.boolean'     => 'validation.breadcrumb_boolean',
            'external_link.url'      => 'validation.external_link_url',
            'status.required'        => 'validation.status_invalid',
            'status.integer'         => 'validation.status_invalid',
            'status.in'              => 'validation.status_invalid',
            'sort.required'          => 'validation.sort_integer',
            'sort.integer'           => 'validation.sort_integer',
            'sort.min'               => 'validation.sort_min',
        ];
    }
}
