<?php

declare(strict_types=1);

namespace app\adminapi\controller\region;

use app\adminapi\controller\AuthenticatedController;
use app\service\region\RegionService;
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
 * 地区。
 *
 * 端点（静态路径由 FastRoute 先匹配，不靠方法声明顺序）：
 *   GET    /adminapi/region/list              index   region.list
 *   GET    /adminapi/region/tree              tree    PermissionSkip
 *   GET    /adminapi/common/regions           tree    PermissionSkip（与 tree 同一动作）
 *   GET    /adminapi/region/detail/{id}       show    region.list
 *   POST   /adminapi/region                   store   region.create
 *   PUT    /adminapi/region/{id}              update  region.update
 *   DELETE /adminapi/region/{id}              delete  region.delete
 *
 * update 场景用 sometimes|required：字段不传时跳过（局部更新），传了空值必须校验失败。
 * 不收 level：由 Service 按父级重算。
 * 唯一性不在这里做成校验规则，由 RegionService 查重 + 唯一索引异常兜底（422 errors.code）。
 *
 * store()/update() 各自的校验规则由同名的 xxxRules() 无参私有方法提供。
 */
#[RouteGroup('/adminapi')]
class RegionController extends AuthenticatedController
{
    #[Inject]
    protected RegionService $regionService;

    #[Get('/region/list')]
    #[Permission('region.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request, 20);

        return $this->paginate($this->regionService->getList((array) $request->get(), $page, $limit));
    }

    #[Get('/region/tree')]
    #[Get('/common/regions')]
    #[PermissionSkip]
    public function tree(): Response
    {
        return $this->success($this->regionService->getTree());
    }

    #[Get('/region/detail/{id:\d+}')]
    #[Permission('region.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->regionService->getDetail((int) $id), lang('messages.get_success'));
    }

    #[Post('/region')]
    #[Permission('region.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

        return $this->success($this->regionService->create($data), lang('messages.create_success'));
    }

    #[Put('/region/{id:\d+}')]
    #[Permission('region.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());
        $this->regionService->update((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Delete('/region/{id:\d+}')]
    #[Permission('region.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->regionService->delete((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    /**
     * @return array<string, string>
     */
    private function storeRules(): array
    {
        return [
            'parent_id' => 'integer',
            'name'      => 'required|string|max:50',
            'code'      => 'required|string|max:20',
            'sort'      => 'integer|min:0',
            'status'    => 'in:0,1',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function updateRules(): array
    {
        return [
            'parent_id' => 'sometimes|required|integer',
            'name'      => 'sometimes|required|string|max:50',
            'code'      => 'sometimes|required|string|max:20',
            'sort'      => 'sometimes|required|integer|min:0',
            'status'    => 'sometimes|required|in:0,1',
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
            'sort.required'      => 'region.region_sort_require',
            'sort.integer'       => 'region.region_sort_integer',
            'sort.min'           => 'region.region_sort_min',
            'status.required'    => 'region.region_status_require',
            'status.integer'     => 'region.region_status_integer',
            'status.in'          => 'region.region_status_invalid',
        ];
    }
}
