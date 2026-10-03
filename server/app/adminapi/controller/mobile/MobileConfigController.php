<?php

declare(strict_types=1);

namespace app\adminapi\controller\mobile;

use app\adminapi\controller\AuthenticatedController;
use app\service\mobile\MobileConfigService;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\annotation\route\Get;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/**
 * 管理端移动端配置。
 *
 *   GET /adminapi/mobile/config            show      mobile.config.view
 *   GET /adminapi/mobile/config/eligible   eligible  mobile.config.view
 *   PUT /adminapi/mobile/config            update    mobile.config.update
 *
 * update() 的校验规则由 updateRules() 提供。未知键被 validate 白名单丢掉；
 * tabbar 条数与单项必填由 Service 再验。
 */
#[RouteGroup('/adminapi/mobile')]
class MobileConfigController extends AuthenticatedController
{
    #[Inject]
    protected MobileConfigService $mobileConfigService;

    #[Get('/config')]
    #[Permission('mobile.config.view')]
    public function show(): Response
    {
        return $this->success($this->mobileConfigService->get());
    }

    #[Get('/config/eligible')]
    #[Permission('mobile.config.view')]
    public function eligible(): Response
    {
        return $this->success($this->mobileConfigService->listEligible());
    }

    #[Put('/config')]
    #[Permission('mobile.config.update')]
    public function update(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules());

        return $this->success($this->mobileConfigService->save($data), lang('messages.update_success'));
    }

    /**
     * @return array<string, string>
     */
    private function updateRules(): array
    {
        return [
            'app_name'      => 'sometimes',
            'app_logo'      => 'sometimes',
            'theme_color'   => 'sometimes',
            'theme_colors'  => 'nullable|array',
            'home_app_code' => 'sometimes',
            'home_page'     => 'sometimes',
            'tabbar'        => 'nullable|array',
            'tabbar_style'  => 'nullable|array',
            'status'        => 'sometimes|in:0,1',
        ];
    }
}
