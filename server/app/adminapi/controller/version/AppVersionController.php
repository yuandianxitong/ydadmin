<?php

declare(strict_types=1);

namespace app\adminapi\controller\version;

use app\service\version\AppVersionService;
use core\base\Controller;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 应用版本（由代码生成器生成）。
 *
 * 端点（具名/静态路径必须排在 {id} 通配路由之前注册，见 config/route/version.php）：
 *   GET    /adminapi/version/list              index   version.list
 *   GET    /adminapi/version/detail/{id}       show    version.list
 *   POST   /adminapi/version                   store   version.create
 *   PUT    /adminapi/version/{id}              update  version.update
 *   DELETE /adminapi/version/{id}              delete  version.delete
 *
 * 无独立 status / batchDelete 路由：启用/禁用走 update 的 status 字段。
 * update 场景用 sometimes|required：字段不传时跳过（局部更新），传了空值必须校验失败。
 * 唯一性不在这里做成校验规则，由 AppVersionService 查重 + 唯一索引异常兜底（spec 决策 12）。
 *
 * store()/update() 各自的校验规则由同名的 xxxRules() 无参私有方法
 * 提供：M2b 的 RuleReflector 按动作名反射调用 "{action}Rules"（spec §5、§14），方法必须无参、
 * 纯函数——少了任何一个动作的这层包装，文档就会静默漏掉那个端点的参数，且不会有任何报错
 * （check:context 规则七拦这个，见 scripts/check-context-discipline.sh）。
 */
class AppVersionController extends Controller
{
    #[Inject]
    protected AppVersionService $appVersionService;

    #[Permission('version.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request, 20);

        return $this->paginate($this->appVersionService->getAppVersionList((array) $request->get(), $page, $limit));
    }

    #[Permission('version.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->appVersionService->getAppVersionDetail((int) $id), lang('messages.get_success'));
    }

    #[Permission('version.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

        return $this->success($this->appVersionService->createAppVersion($data), lang('messages.create_success'));
    }

    #[Permission('version.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());
        $this->appVersionService->updateAppVersion((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Permission('version.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->appVersionService->deleteAppVersion((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    /**
     * store 场景的字段校验规则。M2b 的 RuleReflector 按动作名反射调用 "storeRules"（spec §5、§14），
     * 这里薄包装委派给 appVersionRules()：规则表只在那一处维护，不重复写。
     *
     * @return array<string, string>
     */
    private function storeRules(): array
    {
        return $this->appVersionRules('create');
    }

    /**
     * update 场景同上，见 storeRules() 的说明。
     *
     * @return array<string, string>
     */
    private function updateRules(): array
    {
        return $this->appVersionRules('update');
    }

    /**
     * store/update 共用的字段校验规则表，由 storeRules()/updateRules() 按场景委派调用（不再被
     * store()/update() 直接调用）。create 场景必填、update 场景 sometimes|required（不传就跳过，
     * 传了空值要拒绝）。
     *
     * 这个数组必须一直是字面量：键是字段名、值是字符串，唯一允许的插值是 {$required}。这不是
     * 给 M2b 反射用的约束（反射直接执行 storeRules()/updateRules() 拿完全求值后的返回值，不管
     * 内部怎么实现）——而是给人读的：这张表本身就是接口契约，写成运行时拼装（foreach 塞、
     * array_merge、变量当键）会让人没法一眼看出这个模块收哪些字段。
     *
     * @return array<string, string>
     */
    private function appVersionRules(string $scene): array
    {
        $required = $scene === 'create' ? 'required' : 'sometimes|required';

        return [
            'platform'     => "{$required}|in:android,ios,harmony",
            'version'      => "{$required}|string|max:20",
            'version_code' => "{$required}|integer|min:1",
            'download_url' => 'sometimes|string|max:500',
            'description'  => 'nullable|string',
            'force_update' => 'sometimes|required|integer|in:0,1',
            'status'       => 'sometimes|required|integer|in:0,1',
        ];
    }

    /**
     * message 的值即 lang key（与 ValidatorFactory::resolveMessage 的约定一致）。
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'platform.required'     => 'version.app_version_platform_require',
            'platform.in'           => 'version.app_version_platform_invalid',
            'version.required'      => 'version.app_version_version_require',
            'version.max'           => 'version.app_version_version_length',
            'version_code.required' => 'version.app_version_version_code_require',
            'version_code.integer'  => 'version.app_version_version_code_integer',
            'version_code.min'      => 'version.app_version_version_code_min',
            'download_url.max'      => 'version.app_version_download_url_length',
            'force_update.required' => 'version.app_version_force_update_require',
            'force_update.integer'  => 'version.app_version_force_update_integer',
            'force_update.in'       => 'version.app_version_force_update_invalid',
            'status.required'       => 'version.app_version_status_require',
            'status.integer'        => 'version.app_version_status_integer',
            'status.in'             => 'version.app_version_status_invalid',
        ];
    }
}
