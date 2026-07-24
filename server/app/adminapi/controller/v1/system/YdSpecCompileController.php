<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use app\service\system\YdSpecCompileService;
use core\attribute\Permission;
use core\base\Controller;

class YdSpecCompileController extends Controller
{
    protected YdSpecCompileService $ydSpecCompileService;

    #[Permission('ai.ydspec.use')]
    public function compile()
    {
        $specId = trim((string) $this->request->post('spec_id', ''));
        if ($specId === '') {
            return $this->error('缺少 spec_id');
        }

        return $this->success('ok', $this->ydSpecCompileService->compile($specId));
    }

    #[Permission('ai.ydspec.use')]
    public function applyDev()
    {
        $specId = trim((string) $this->request->post('spec_id', ''));
        $stageId = trim((string) $this->request->post('stage_id', ''));
        if ($specId === '' || $stageId === '') {
            return $this->error('缺少参数');
        }

        return $this->success('已应用到开发环境', $this->ydSpecCompileService->applyDev($specId, $stageId));
    }
}
