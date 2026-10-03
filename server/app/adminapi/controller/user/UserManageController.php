<?php

declare(strict_types=1);

namespace app\adminapi\controller\user;

use app\adminapi\controller\AuthenticatedController;
use app\service\dataimport\DataImportService;
use app\service\system\SystemConfigService;
use app\service\user\UserManageService;
use core\context\RequestContext;
use core\exception\BusinessException;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;
use Webman\Http\UploadFile;

/**
 * 管理端会员管理（spec §6.3）。
 *
 * 端点（静态路径由 FastRoute 先匹配，不靠方法声明顺序）：
 *   GET  /adminapi/user/list             index          user.list
 *   GET  /adminapi/user/balance-logs     balanceLogs    user.balance-logs
 *   GET  /adminapi/user/points-logs      pointsLogs     user.points-logs
 *   POST /adminapi/user/adjust-balance   adjustBalance  user.adjust-balance
 *   POST /adminapi/user/adjust-points    adjustPoints   user.adjust-points
 *   GET  /adminapi/user/detail/{id}      detail         user.detail
 *   PUT  /adminapi/user/{id}/status      updateStatus   user.status
 *   POST /adminapi/user/import           import         user.import
 *   GET  /adminapi/user/import-template  importTemplate user.import
 *
 * 三个列表动作不调 validate()：查询参数（keyword / status / type / start_date / end_date）
 * 的整形在 Repository 里做，与 DictionaryController::index 一致，规则七不适用。
 * amount 与 points 允许负数（后台调减），但不允许 0，也不允许把结果调成负数——后者由
 * BalanceService / PointsService 抛 422。amount 额外限制最多两位小数（decimal:0,2）：
 * BalanceService::toCents() 会把更细的精度静默四舍五入到分，从入口挡住比事后发现强。
 */
#[RouteGroup('/adminapi/user')]
class UserManageController extends AuthenticatedController
{
    private const DEFAULT_FILE_MAX_MB = 10;

    /** @var array<string, string> */
    private const USER_FIELD_MAP = [
        'mobile'   => 'mobile',
        'nickname' => 'nickname',
        'password' => 'password',
        'email'    => 'email',
        'gender'   => 'gender',
        'status'   => 'status',
    ];

    #[Inject]
    protected UserManageService $userManageService;

    #[Inject]
    protected DataImportService $dataImportService;

    #[Inject]
    protected SystemConfigService $systemConfigService;

    #[Get('/list')]
    #[Permission('user.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->userManageService->getList((array) $request->get(), $page, $limit));
    }

    #[Get('/detail/{id:\d+}')]
    #[Permission('user.detail')]
    public function detail(Request $request, string $id): Response
    {
        return $this->success($this->userManageService->getDetail((int) $id), lang('messages.get_success'));
    }

    #[Post('/adjust-balance')]
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

    #[Post('/adjust-points')]
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

    #[Put('/{id:\d+}/status')]
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

    #[Get('/balance-logs')]
    #[Permission('user.balance-logs')]
    public function balanceLogs(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->userManageService->getBalanceLogs((array) $request->get(), $page, $limit));
    }

    #[Post('/import')]
    #[Permission('user.import')]
    public function import(Request $request): Response
    {
        $file = $request->file('file');
        if (!$file instanceof UploadFile || !$file->isValid()) {
            throw new BusinessException(lang('dataimport.file_required'));
        }
        if (strtolower($file->getUploadExtension()) !== 'csv') {
            throw new BusinessException(lang('dataimport.csv_only'));
        }
        $maxMb = (int) $this->systemConfigService->getConfigValue('storage_upload_max_size', self::DEFAULT_FILE_MAX_MB);
        if ($maxMb <= 0) {
            $maxMb = self::DEFAULT_FILE_MAX_MB;
        }
        if ((int) $file->getSize() > $maxMb * 1024 * 1024) {
            throw new BusinessException(lang('business.file_size_exceeded', ['size' => $maxMb]));
        }

        $dir = runtime_path() . '/imports';
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new BusinessException(lang('dataimport.file_open_failed'));
        }
        $target = $dir . '/' . bin2hex(random_bytes(16)) . '.csv';
        $file->move($target);

        return $this->success($this->dataImportService->import(
            'user',
            $target,
            (string) $file->getUploadName(),
            self::USER_FIELD_MAP,
            RequestContext::actingUser()
        ));
    }

    #[Get('/import-template')]
    #[Permission('user.import')]
    public function importTemplate(): Response
    {
        $csv = "mobile,nickname,password,email,gender,status\n";

        return new Response(200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="user-import.csv"',
        ], $csv);
    }

    #[Get('/points-logs')]
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
