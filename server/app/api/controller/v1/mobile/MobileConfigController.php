<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\api\controller\v1\mobile;

use app\service\mobile\MobileConfigService;
use core\base\Controller;
use think\Response;

class MobileConfigController extends Controller
{
    protected MobileConfigService $configService;

    private const C_SIDE_ALLOW = [
        'app_name', 'app_logo', 'theme_color', 'theme_colors',
        'tabbar', 'tabbar_style', 'home_decoration',
    ];

    public function get(): Response
    {
        $cfg = $this->configService->get();
        $public = array_intersect_key($cfg, array_flip(self::C_SIDE_ALLOW));
        return $this->success('ok', $public);
    }
}
