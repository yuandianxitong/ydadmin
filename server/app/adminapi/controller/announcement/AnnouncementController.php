<?php

declare(strict_types=1);

namespace app\adminapi\controller\announcement;

use app\adminapi\controller\AuthenticatedController;
use app\service\announcement\AnnouncementService;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/**
 * 公告（由代码生成器生成）。
 *
 * 端点（静态路径由 FastRoute 先匹配，不靠方法声明顺序）：
 *   GET    /adminapi/announcement/list              index   announcement.list
 *   GET    /adminapi/announcement/detail/{id}       show    announcement.list
 *   POST   /adminapi/announcement                   store   announcement.create
 *   PUT    /adminapi/announcement/{id}/status       status  announcement.status
 *   PUT    /adminapi/announcement/{id}              update  announcement.update
 *   DELETE /adminapi/announcement/{id}              delete  announcement.delete
 *
 * update 场景用 sometimes|required：字段不传时跳过（局部更新），传了空值必须校验失败。
 * 唯一性不在这里做成校验规则，由 AnnouncementService 查重 + 唯一索引异常兜底（spec 决策 12）。
 *
 * store()/update()/batchDelete()/status() 各自的校验规则由同名的 xxxRules() 无参私有方法
 * 提供：M2b 的 RuleReflector 按动作名反射调用 "{action}Rules"（spec §5、§14），方法必须无参、
 * 纯函数——少了任何一个动作的这层包装，文档就会静默漏掉那个端点的参数，且不会有任何报错
 * （check:context 规则七拦这个，见 scripts/check-context-discipline.sh）。
 */
#[RouteGroup('/adminapi/announcement')]
class AnnouncementController extends AuthenticatedController
{
    #[Inject]
    protected AnnouncementService $announcementService;

    #[Get('/list')]
    #[Permission('announcement.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request, 20);

        return $this->paginate($this->announcementService->getAnnouncementList((array) $request->get(), $page, $limit));
    }

    #[Get('/detail/{id:\d+}')]
    #[Permission('announcement.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->announcementService->getAnnouncementDetail((int) $id), lang('messages.get_success'));
    }

    #[Post('')]
    #[Permission('announcement.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

        return $this->success($this->announcementService->createAnnouncement($data), lang('messages.create_success'));
    }

    #[Put('/{id:\d+}')]
    #[Permission('announcement.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());
        $this->announcementService->updateAnnouncement((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Delete('/{id:\d+}')]
    #[Permission('announcement.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->announcementService->deleteAnnouncement((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Permission('announcement.delete')]
    public function batchDelete(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->batchDeleteRules(), [
            'ids.required'  => 'announcement.announcement_ids_require',
            'ids.array'     => 'announcement.announcement_ids_require',
            'ids.min'       => 'announcement.announcement_ids_require',
            'ids.*.integer' => 'announcement.announcement_ids_integer',
        ]);
        $this->announcementService->batchDelete(array_map('intval', (array) $data['ids']));

        return $this->success([], lang('messages.batch_delete_success'));
    }

    #[Put('/{id:\d+}/status')]
    #[Permission('announcement.status')]
    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->statusRules(), [
            'status.required' => 'announcement.announcement_status_require',
            'status.integer'  => 'announcement.announcement_status_integer',
            'status.in'       => 'announcement.announcement_status_invalid',
        ]);
        $this->announcementService->updateStatus((int) $id, (int) $data['status']);

        return $this->success([], lang('messages.status_update_success'));
    }

    /**
     * store 场景的字段校验规则。M2b 的 RuleReflector 按动作名反射调用 "storeRules"（spec §5、§14），
     * 这里薄包装委派给 announcementRules()：规则表只在那一处维护，不重复写。
     *
     * @return array<string, string>
     */
    private function storeRules(): array
    {
        return $this->announcementRules('create');
    }

    /**
     * update 场景同上，见 storeRules() 的说明。
     *
     * @return array<string, string>
     */
    private function updateRules(): array
    {
        return $this->announcementRules('update');
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
    private function announcementRules(string $scene): array
    {
        $required = $scene === 'create' ? 'required' : 'sometimes|required';

        return [
            'title'      => "{$required}|string|max:200",
            'content'    => 'nullable|string',
            'type'       => "{$required}|integer|in:1,2,3",
            'status'     => 'sometimes|required|integer|in:0,1',
            'sort'       => 'sometimes|required|integer|min:0',
            'publish_at' => 'nullable|date',
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
            'title.required'  => 'announcement.announcement_title_require',
            'title.max'       => 'announcement.announcement_title_length',
            'type.required'   => 'announcement.announcement_type_require',
            'type.integer'    => 'announcement.announcement_type_integer',
            'type.in'         => 'announcement.announcement_type_invalid',
            'status.required' => 'announcement.announcement_status_require',
            'status.integer'  => 'announcement.announcement_status_integer',
            'status.in'       => 'announcement.announcement_status_invalid',
            'sort.required'   => 'announcement.announcement_sort_require',
            'sort.integer'    => 'announcement.announcement_sort_integer',
            'sort.min'        => 'announcement.announcement_sort_min',
            'publish_at.date' => 'announcement.announcement_publish_at_date',
        ];
    }
}
