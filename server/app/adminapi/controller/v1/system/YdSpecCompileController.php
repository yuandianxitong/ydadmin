<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use app\service\system\AiArtifactService;
use app\service\system\YdSpecCompileService;
use core\attribute\Permission;
use core\base\Controller;

class YdSpecCompileController extends Controller
{
    protected YdSpecCompileService $ydSpecCompileService;
    protected AiArtifactService $aiArtifactService;

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
    public function artifacts()
    {
        $specId = trim((string) $this->request->get('spec_id', ''));
        if ($specId === '') {
            return $this->error('缺少 spec_id');
        }
        return $this->success('ok', $this->aiArtifactService->listBySpec($specId));
    }

    #[Permission('ai.ydspec.use')]
    public function recheck($id)
    {
        $artifactId = (int) $id;
        if ($artifactId <= 0) {
            return $this->error('缺少 artifact id');
        }
        return $this->success('ok', $this->aiArtifactService->runChecks($artifactId));
    }

    #[Permission('ai.ydspec.apply')]
    public function apply($id)
    {
        $artifactId = (int) $id;
        if ($artifactId <= 0) {
            return $this->error('缺少 artifact id');
        }
        return $this->success('已应用到开发环境', $this->aiArtifactService->applyArtifact($artifactId));
    }

    #[Permission('ai.ydspec.apply')]
    public function applyDevCompat()
    {
        $specId  = trim((string) $this->request->post('spec_id', ''));
        $stageId = trim((string) $this->request->post('stage_id', ''));
        if ($specId === '' || $stageId === '') {
            return $this->error('缺少参数');
        }
        return $this->success('已应用到开发环境', $this->ydSpecCompileService->applyDev($specId, $stageId));
    }
}
