<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\mobile;

use app\service\mobile\MobileConfigService;
use core\attribute\Permission;
use core\base\Controller;
use think\Response;

class MobileConfigController extends Controller
{
    protected MobileConfigService $configService;

    #[Permission('mobile.config.view')]
    public function get(): Response
    {
        return $this->success('ok', $this->configService->get());
    }

    #[Permission('mobile.config.update')]
    public function update(): Response
    {
        $saved = $this->configService->save($this->request->put());
        return $this->success('updated', $saved);
    }

    #[Permission('mobile.config.view')]
    public function eligible(): Response
    {
        return $this->success('ok', $this->configService->listEligible());
    }
}
