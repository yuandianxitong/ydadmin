<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\service\system\LogService;
use core\base\Controller;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 登录日志与操作日志（契约 §2.8）。列表、删除、清空都只作用于当前管理员数据范围内的日志（spec §5.5）。
 * 筛选条件先经 validate()，返回值即筛选白名单；清空返回 {count}（与批量删除一致，前端不读 data）。
 */
class LogController extends Controller
{
    #[Inject]
    protected LogService $logService;

    #[Permission('system.log.login')]
    public function loginLog(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);
        $params = $this->validate((array) $request->get(), $this->loginLogRules(), $this->filterMessages());

        return $this->paginate($this->logService->getLoginLogList($params, $page, $limit));
    }

    #[Permission('system.log.operation')]
    public function operationLog(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);
        $params = $this->validate((array) $request->get(), $this->operationLogRules(), $this->filterMessages());

        return $this->paginate($this->logService->getOperationLogList($params, $page, $limit));
    }

    #[Permission('system.log.delete')]
    public function deleteLoginLog(Request $request, string $id): Response
    {
        $this->logService->deleteLoginLog((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Permission('system.log.delete')]
    public function deleteOperationLog(Request $request, string $id): Response
    {
        $this->logService->deleteOperationLog((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Permission('system.log.clear')]
    public function clearLoginLog(): Response
    {
        return $this->success(['count' => $this->logService->clearLoginLogs()], lang('messages.clear_success'));
    }

    #[Permission('system.log.clear')]
    public function clearOperationLog(): Response
    {
        return $this->success(['count' => $this->logService->clearOperationLogs()], lang('messages.clear_success'));
    }

    /** @return array<string, string> */
    private function loginLogRules(): array
    {
        return [
            'keyword'      => 'nullable|string|max:50',
            'ip'           => 'nullable|string|max:45',
            'login_result' => 'nullable|in:0,1',
            'start_date'   => 'nullable|date_format:Y-m-d',
            'end_date'     => 'nullable|date_format:Y-m-d',
        ];
    }

    /** @return array<string, string> */
    private function operationLogRules(): array
    {
        return [
            'keyword'    => 'nullable|string|max:50',
            'method'     => 'nullable|string|max:10',
            'path'       => 'nullable|string|max:255',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d',
        ];
    }

    /** @return array<string, string> 值即 lang key */
    private function filterMessages(): array
    {
        return [
            'keyword.string'         => 'validation.log_filter_invalid',
            'keyword.max'            => 'validation.log_filter_too_long',
            'ip.string'              => 'validation.log_filter_invalid',
            'ip.max'                 => 'validation.log_filter_too_long',
            'method.string'          => 'validation.log_filter_invalid',
            'method.max'             => 'validation.log_filter_too_long',
            'path.string'            => 'validation.log_filter_invalid',
            'path.max'               => 'validation.log_filter_too_long',
            'login_result.in'        => 'validation.login_result_invalid',
            'start_date.date_format' => 'validation.log_date_format',
            'end_date.date_format'   => 'validation.log_date_format',
        ];
    }
}
