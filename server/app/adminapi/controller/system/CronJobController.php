<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\AuthenticatedController;
use app\service\system\CronJobService;
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
 * 定时任务（spec §3、§7）。
 *
 * 端点（静态路径由 FastRoute 先匹配，不靠方法声明顺序）：
 *   GET    /adminapi/system/cron-job                    index      system.cron_job.list
 *   GET    /adminapi/system/cron-job/{id}/logs          logs       system.cron_job.list
 *   POST   /adminapi/system/cron-job/{id}/clear-logs    clearLogs  system.cron_job.clear
 *   PUT    /adminapi/system/cron-job/{id}/status        status     system.cron_job.update
 *   POST   /adminapi/system/cron-job/{id}/run           run        system.cron_job.run
 *   GET    /adminapi/system/cron-job/{id}               show       system.cron_job.list
 *   POST   /adminapi/system/cron-job                    store      system.cron_job.create
 *   PUT    /adminapi/system/cron-job/{id}               update     system.cron_job.update
 *   DELETE /adminapi/system/cron-job/{id}               delete     system.cron_job.delete
 *
 * 表达式的请求字段叫 cron_expression（前端表单），入库列叫 expression；响应两个键都给（见 CronJobService）。
 * update 用 sometimes|required：不传跳过（部分更新），传了空值必须校验失败。
 */
#[RouteGroup('/adminapi/system/cron-job')]
class CronJobController extends AuthenticatedController
{
    #[Inject]
    protected CronJobService $cronJobService;

    #[Get('')]
    #[Permission('system.cron_job.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);
        $params = $this->validate((array) $request->get(), $this->indexRules(), $this->messages());

        return $this->paginate($this->cronJobService->getList($params, $page, $limit));
    }

    #[Get('/{id:\d+}')]
    #[Permission('system.cron_job.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->cronJobService->getDetail((int) $id), lang('messages.get_success'));
    }

    #[Post('')]
    #[Permission('system.cron_job.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

        return $this->success($this->cronJobService->create($data), lang('messages.create_success'));
    }

    #[Put('/{id:\d+}')]
    #[Permission('system.cron_job.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());
        $this->cronJobService->update((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Delete('/{id:\d+}')]
    #[Permission('system.cron_job.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->cronJobService->delete((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Put('/{id:\d+}/status')]
    #[Permission('system.cron_job.update')]
    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->statusRules(), $this->messages());
        $this->cronJobService->updateStatus((int) $id, (int) $data['status']);

        return $this->success([], lang('messages.status_update_success'));
    }

    #[Get('/{id:\d+}/logs')]
    #[Permission('system.cron_job.list')]
    public function logs(Request $request, string $id): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->cronJobService->getLogs((int) $id, $page, $limit));
    }

    #[Post('/{id:\d+}/clear-logs')]
    #[Permission('system.cron_job.clear')]
    public function clearLogs(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->clearLogsRules(), $this->messages());
        $keepDays = isset($data['keep_days']) ? (int) $data['keep_days'] : 30;

        return $this->success(['count' => $this->cronJobService->clearLogs((int) $id, $keepDays)], lang('messages.clear_success'));
    }

    /** 手动执行：data = {status: 0|1, output}（前端 status === 1 才提示成功）。 */
    #[Post('/{id:\d+}/run')]
    #[Permission('system.cron_job.run')]
    public function run(Request $request, string $id): Response
    {
        return $this->success($this->cronJobService->runNow((int) $id), lang('messages.success'));
    }

    /** @return array<string, string> */
    private function indexRules(): array
    {
        return [
            'keyword' => 'nullable|string|max:100',
            'status'  => 'nullable|in:0,1',
        ];
    }

    /** @return array<string, string> */
    private function storeRules(): array
    {
        return [
            'name'            => 'required|string|max:100',
            'command'         => 'required|string|max:255',
            'cron_expression' => 'required|string|max:100',
            'description'     => 'nullable|string|max:255',
            'sort'            => 'sometimes|required|integer|min:0|max:9999',
            'status'          => 'sometimes|required|integer|in:0,1',
        ];
    }

    /** @return array<string, string> */
    private function updateRules(): array
    {
        return [
            'name'            => 'sometimes|required|string|max:100',
            'command'         => 'sometimes|required|string|max:255',
            'cron_expression' => 'sometimes|required|string|max:100',
            'description'     => 'nullable|string|max:255',
            'sort'            => 'sometimes|required|integer|min:0|max:9999',
            'status'          => 'sometimes|required|integer|in:0,1',
        ];
    }

    /** @return array<string, string> */
    private function statusRules(): array
    {
        return ['status' => 'required|integer|in:0,1'];
    }

    /** @return array<string, string> */
    private function clearLogsRules(): array
    {
        return ['keep_days' => 'nullable|integer|min:0|max:3650'];
    }

    /**
     * message 值即 lang key（与 ValidatorFactory::resolveMessage 的约定一致）。
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'keyword.string'           => 'validation.cron_keyword_max',
            'keyword.max'              => 'validation.cron_keyword_max',
            'name.required'            => 'validation.cron_name_require',
            'name.string'              => 'validation.cron_name_require',
            'name.max'                 => 'validation.cron_name_max',
            'command.required'         => 'validation.cron_command_require',
            'command.string'           => 'validation.cron_command_require',
            'command.max'              => 'validation.cron_command_max',
            'cron_expression.required' => 'validation.cron_expression_require',
            'cron_expression.string'   => 'validation.cron_expression_require',
            'cron_expression.max'      => 'validation.cron_expression_max',
            'description.string'       => 'validation.cron_description_max',
            'description.max'          => 'validation.cron_description_max',
            'sort.required'            => 'validation.sort_integer',
            'sort.integer'             => 'validation.sort_integer',
            'sort.min'                 => 'validation.sort_min',
            'sort.max'                 => 'validation.cron_sort_max',
            'status.required'          => 'validation.status_invalid',
            'status.integer'           => 'validation.status_integer',
            'status.in'                => 'validation.status_invalid',
            'keep_days.integer'        => 'validation.cron_keep_days_invalid',
            'keep_days.min'            => 'validation.cron_keep_days_invalid',
            'keep_days.max'            => 'validation.cron_keep_days_invalid',
        ];
    }
}
