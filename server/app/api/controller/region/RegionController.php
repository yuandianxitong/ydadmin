<?php

declare(strict_types=1);

namespace app\api\controller\region;

use app\service\region\RegionService;
use core\base\Controller;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * C 端地区只读。公开，不挂 ApiAuthMiddleware。
 *
 *   GET /api/region/tree       tree      仅启用 {value,label,children?}
 *   GET /api/region/children   children  仅启用完整行
 *
 * 权限点体系是管理端的，C 端方法一律 #[PermissionSkip]。
 */
class RegionController extends Controller
{
    #[Inject]
    protected RegionService $regionService;

    #[PermissionSkip]
    public function tree(): Response
    {
        return $this->success($this->regionService->getTree());
    }

    #[PermissionSkip]
    public function children(Request $request): Response
    {
        return $this->success($this->regionService->getChildren((int) $request->get('parent_id', 0)));
    }
}
