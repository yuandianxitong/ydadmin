<?php

declare(strict_types=1);

namespace app\api\controller\agreement;

use app\service\agreement\AgreementService;
use core\base\Controller;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * C 端协议只读（M7a spec §5）。公开，不挂 ApiAuthMiddleware。
 *
 *   GET /api/agreement/{code}    show    已启用详情；禁用 / 不存在 → body.code 404
 *
 * 权限点体系是管理端的，C 端方法一律 #[PermissionSkip]。
 */
class AgreementController extends Controller
{
    #[Inject]
    protected AgreementService $agreementService;

    #[PermissionSkip]
    public function show(Request $request, string $code): Response
    {
        return $this->success($this->agreementService->getPublishedByCode($code), lang('messages.get_success'));
    }
}
