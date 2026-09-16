<?php

declare(strict_types=1);

namespace app\adminapi\controller\user;

use app\service\user\UserManageService;
use core\base\Controller;
use core\context\RequestContext;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 管理端会员管理（spec §6.3）。
 *
 * 端点（具名路由都在 {id} 通配路由之前注册）：
 *   GET  /adminapi/user/list             index          user.list
 *   GET  /adminapi/user/balance-logs     balanceLogs    user.balance-logs
 *   GET  /adminapi/user/points-logs      pointsLogs     user.points-logs
 *   POST /adminapi/user/adjust-balance   adjustBalance  user.adjust-balance
 *   POST /adminapi/user/adjust-points    adjustPoints   user.adjust-points
 *   GET  /adminapi/user/detail/{id}      detail         user.detail
 *   PUT  /adminapi/user/{id}/status      updateStatus   user.status
 *
 * 三个列表动作不调 validate()：查询参数（keyword / status / type / start_date / end_date）
 * 的整形在 Repository 里做，与 DictionaryController::index 一致，规则七不适用。
 * amount 与 points 允许负数（后台调减），但不允许 0，也不允许把结果调成负数——后者由
 * BalanceService / PointsService 抛 422。amount 额外限制最多两位小数（decimal:0,2）：
 * BalanceService::toCents() 会把更细的精度静默四舍五入到分，从入口挡住比事后发现强。
 */
class UserManageController extends Controller
{
    #[Inject]
    protected UserManageService $userManageService;

    #[Permission('user.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->userManageService->getList((array) $request->get(), $page, $limit));
    }

    #[Permission('user.detail')]
    public function detail(Request $request, string $id): Response
    {
        return $this->success($this->userManageService->getDetail((int) $id), lang('messages.get_success'));
    }

    #[Permission('user.adjust-balance')]
    public function adjustBalance(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->adjustBalanceRules(), [
            'user_id.required' => 'validation.user_id_require',
            'user_id.integer'  => 'validation.user_id_require',
            'user_id.min'      => 'validation.user_id_require',
            'amount.required'  => 'validation.amount_invalid',
            'amount.numeric'   => 'validation.amount_invalid',
            'amount.between'   => 'validation.amount_invalid',
            'amount.decimal'   => 'validation.amount_invalid',
            'amount.not_in'    => 'validation.amount_zero',
            'remark.max'       => 'validation.remark_max',
        ]);
        $this->userManageService->adjustBalance(
            (int) $data['user_id'],
            (float) $data['amount'],
            (string) ($data['remark'] ?? ''),
            RequestContext::actingUser()
        );

        return $this->success([], lang('messages.update_success'));
    }

    #[Permission('user.adjust-points')]
    public function adjustPoints(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->adjustPointsRules(), [
            'user_id.required' => 'validation.user_id_require',
            'user_id.integer'  => 'validation.user_id_require',
            'user_id.min'      => 'validation.user_id_require',
            'points.required'  => 'validation.points_invalid',
            'points.integer'   => 'validation.points_invalid',
            'points.between'   => 'validation.points_invalid',
            'points.not_in'    => 'validation.points_zero',
            'remark.max'       => 'validation.remark_max',
        ]);
        $this->userManageService->adjustPoints(
            (int) $data['user_id'],
            (int) $data['points'],
            (string) ($data['remark'] ?? ''),
            RequestContext::actingUser()
        );

        return $this->success([], lang('messages.update_success'));
    }

    #[Permission('user.status')]
    public function updateStatus(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateStatusRules(), [
            'status.required' => 'validation.status_invalid',
            'status.integer'  => 'validation.status_integer',
            'status.in'       => 'validation.status_invalid',
        ]);
        $this->userManageService->updateStatus((int) $id, (int) $data['status']);

        return $this->success([], lang('messages.status_update_success'));
    }

    #[Permission('user.balance-logs')]
    public function balanceLogs(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->userManageService->getBalanceLogs((array) $request->get(), $page, $limit));
    }

    #[Permission('user.points-logs')]
    public function pointsLogs(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->userManageService->getPointsLogs((array) $request->get(), $page, $limit));
    }

    /** @return array<string, string> */
    private function adjustBalanceRules(): array
    {
        return [
            'user_id' => 'required|integer|min:1',
            'amount'  => 'required|numeric|not_in:0|between:-99999999.99,99999999.99|decimal:0,2',
            'remark'  => 'nullable|string|max:255',
        ];
    }

    /** @return array<string, string> */
    private function adjustPointsRules(): array
    {
        return [
            'user_id' => 'required|integer|min:1',
            'points'  => 'required|integer|not_in:0|between:-99999999,99999999',
            'remark'  => 'nullable|string|max:255',
        ];
    }

    /** @return array<string, string> */
    private function updateStatusRules(): array
    {
        return ['status' => 'required|integer|in:0,1'];
    }
}
