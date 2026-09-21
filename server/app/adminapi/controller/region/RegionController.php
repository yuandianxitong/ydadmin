<?php

declare(strict_types=1);

namespace app\adminapi\controller\region;

use app\service\region\RegionService;
use core\base\Controller;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 地区（由代码生成器生成）。
 *
 * 端点（具名/静态路径必须排在 {id} 通配路由之前注册，见 config/route/region.php）：
 *   GET    /adminapi/region/list              index   region.list
 *   GET    /adminapi/region/detail/{id}       show    region.list
 *   POST   /adminapi/region                   store   region.create
 *   PUT    /adminapi/region/{id}              update  region.update
 *   DELETE /adminapi/region/{id}              delete  region.delete
 *
 * update 场景用 sometimes|required：字段不传时跳过（局部更新），传了空值必须校验失败。
 * 唯一性不在这里做成校验规则，由 RegionService 查重 + 唯一索引异常兜底（spec 决策 12）。
 *
 * store()/update()/batchDelete()/status() 各自的校验规则由同名的 xxxRules() 无参私有方法
 * 提供：M2b 的 RuleReflector 按动作名反射调用 "{action}Rules"（spec §5、§14），方法必须无参、
 * 纯函数——少了任何一个动作的这层包装，文档就会静默漏掉那个端点的参数，且不会有任何报错
 * （check:context 规则七拦这个，见 scripts/check-context-discipline.sh）。
 */
class RegionController extends Controller
{
    #[Inject]
    protected RegionService $regionService;

    #[Permission('region.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request, 20);

        return $this->paginate($this->regionService->getRegionList((array) $request->get(), $page, $limit));
    }

    #[Permission('region.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->regionService->getRegionDetail((int) $id), lang('messages.get_success'));
    }

    #[Permission('region.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

        return $this->success($this->regionService->createRegion($data), lang('messages.create_success'));
    }

    #[Permission('region.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());
        $this->regionService->updateRegion((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Permission('region.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->regionService->deleteRegion((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Permission('region.delete')]
    public function batchDelete(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->batchDeleteRules(), [
            'ids.required'  => 'region.region_ids_require',
            'ids.array'     => 'region.region_ids_require',
            'ids.min'       => 'region.region_ids_require',
            'ids.*.integer' => 'region.region_ids_integer',
        ]);
        $this->regionService->batchDelete(array_map('intval', (array) $data['ids']));

        return $this->success([], lang('messages.batch_delete_success'));
    }

    #[Permission('region.update')]
    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->statusRules(), [
            'status.required' => 'region.region_status_require',
            'status.integer'  => 'region.region_status_integer',
            'status.in'       => 'region.region_status_invalid',
        ]);
        $this->regionService->updateStatus((int) $id, (int) $data['status']);

        return $this->success([], lang('messages.status_update_success'));
    }

    /**
     * store 场景的字段校验规则。M2b 的 RuleReflector 按动作名反射调用 "storeRules"（spec §5、§14），
     * 这里薄包装委派给 regionRules()：规则表只在那一处维护，不重复写。
     *
     * @return array<string, string>
     */
    private function storeRules(): array
    {
        return $this->regionRules('create');
    }

    /**
     * update 场景同上，见 storeRules() 的说明。
     *
     * @return array<string, string>
     */
    private function updateRules(): array
    {
        return $this->regionRules('update');
    }

    /**
     * 批量删除固定校验：ids 必须是非空数组，元素必须是整数（CLAUDE.md + spec §6.1 第 4 条）。
     *
     * @return array<string, string>
     */
    private function batchDeleteRules(): array
    {
        return [
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ];
    }

    /**
     * status 端点固定校验：0/1 二值开关。
     *
     * @return array<string, string>
     */
    private function statusRules(): array
    {
        return ['status' => 'required|integer|in:0,1'];
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
    private function regionRules(string $scene): array
    {
        $required = $scene === 'create' ? 'required' : 'sometimes|required';

        return [
            'parent_id' => 'sometimes|required|integer',
            'name'      => "{$required}|string|max:50",
            'code'      => 'sometimes|required|string|max:20',
            'level'     => 'sometimes|required|integer',
            'sort'      => 'sometimes|required|integer|min:0',
            'status'    => 'sometimes|required|integer|in:0,1',
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
            'parent_id.required' => 'region.region_parent_id_require',
            'parent_id.integer'  => 'region.region_parent_id_integer',
            'name.required'      => 'region.region_name_require',
            'name.max'           => 'region.region_name_length',
            'code.required'      => 'region.region_code_require',
            'code.max'           => 'region.region_code_length',
            'level.required'     => 'region.region_level_require',
            'level.integer'      => 'region.region_level_integer',
            'sort.required'      => 'region.region_sort_require',
            'sort.integer'       => 'region.region_sort_integer',
            'sort.min'           => 'region.region_sort_min',
            'status.required'    => 'region.region_status_require',
            'status.integer'     => 'region.region_status_integer',
            'status.in'          => 'region.region_status_invalid',
        ];
    }
}
